<?php

use App\Enums\OrderStatus;
use App\Filament\Pages\AssessmentMatrix;
use App\Models\Assessment;
use App\Models\AssessmentHistory;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\User;
use Database\Seeders\CriteriaSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
});

function cell(string $code): string
{
    return 'c_'.test()->criteria[$code]->id;
}

function editCell(string $code, ProductionOrder $order): TestAction
{
    return TestAction::make('edit_'.test()->criteria[$code]->id)->table($order);
}

it('opens for the manager and the supervisor only', function (): void {
    foreach ([User::factory()->manager(), User::factory()->supervisor()] as $factory) {
        $this->flushSession();
        $this->actingAs($factory->create())->get('/admin/penilaian-order')->assertOk();
    }

    foreach ([User::factory()->admin(), User::factory()->leader()] as $factory) {
        $this->flushSession();
        $this->actingAs($factory->create())->get('/admin/penilaian-order')->assertForbidden();
    }
});

it('lists the open orders only', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $queued = ProductionOrder::factory()->create();
    $inProgress = ProductionOrder::factory()->inProgress()->create();
    $done = ProductionOrder::factory()->status(OrderStatus::Done)->create();
    $canceled = ProductionOrder::factory()->status(OrderStatus::Canceled)->create();

    Livewire::test(AssessmentMatrix::class)
        ->assertCanSeeTableRecords([$queued, $inProgress])
        ->assertCanNotSeeTableRecords([$done, $canceled]);
});

it('shows a new order straight away with its automatic values filled in', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $order = ProductionOrder::factory()
        ->for(Customer::factory()->urgency(4))
        ->enteredDaysAgo(10)
        ->dueInDays(3)
        ->create(['quantity' => 200, 'unit_price' => 150000]);

    Livewire::test(AssessmentMatrix::class)
        ->assertTableColumnStateSet(cell('C1'), '3 hari', $order)
        ->assertTableColumnHasDescription(cell('C1'), 'Skala 4, otomatis', $order)
        ->assertTableColumnStateSet(cell('C2'), '4', $order)
        ->assertTableColumnStateSet(cell('C6'), 'Rp 30.000.000', $order)
        ->assertTableColumnHasDescription(cell('C6'), 'Skala 3, otomatis', $order)
        ->assertTableColumnStateSet(cell('C7'), '10 hari', $order)
        ->assertTableColumnHasDescription(cell('C7'), 'Skala 5, otomatis', $order);
});

it('shows an empty manual value as not filled in with the worst scale', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create();

    Livewire::test(AssessmentMatrix::class)
        ->assertTableColumnStateSet(cell('C3'), 'Belum diisi', $order)
        ->assertTableColumnHasDescription(cell('C3'), 'Skala 1, belum diisi', $order);
});

it('shows a manual value with its scale and flags a stale one', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create();

    Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => $this->criteria['C3']->id, 'raw_value' => 80, 'value_updated_at' => now()]);
    Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => $this->criteria['C4']->id, 'raw_value' => 20, 'value_updated_at' => now()->subDays(4)]);

    Livewire::test(AssessmentMatrix::class)
        ->assertTableColumnStateSet(cell('C3'), '80%', $order)
        ->assertTableColumnHasDescription(cell('C3'), 'Skala 4, manual', $order)
        ->assertTableColumnStateSet(cell('C4'), '20 jam', $order)
        ->assertTableColumnHasDescription(cell('C4'), 'Skala 4, manual, perlu diperbarui', $order);
});

it('shows the completeness of each order', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $criteria = $this->criteria;
    $complete = ProductionOrder::factory()->create(['unit_price' => 100000]);
    $stale = ProductionOrder::factory()->create(['unit_price' => 100000]);
    $incomplete = ProductionOrder::factory()->create(['unit_price' => 100000]);

    foreach (['C3', 'C4', 'C5'] as $code) {
        Assessment::factory()->create(['production_order_id' => $complete->id, 'criterion_id' => $criteria[$code]->id, 'value_updated_at' => now()]);
        Assessment::factory()->create(['production_order_id' => $stale->id, 'criterion_id' => $criteria[$code]->id, 'value_updated_at' => now()->subDays($code === 'C5' ? 9 : 0)]);
    }

    Assessment::factory()->create(['production_order_id' => $incomplete->id, 'criterion_id' => $criteria['C3']->id, 'value_updated_at' => now()]);

    Livewire::test(AssessmentMatrix::class)
        ->assertTableColumnStateSet('completeness', 'Lengkap', $complete)
        ->assertTableColumnStateSet('completeness', 'Perlu diperbarui', $stale)
        ->assertTableColumnStateSet('completeness', 'Belum lengkap', $incomplete);
});

it('filters the orders that are incomplete or stale', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $criteria = $this->criteria;
    $complete = ProductionOrder::factory()->create(['unit_price' => 100000]);
    $stale = ProductionOrder::factory()->create(['unit_price' => 100000]);
    $incomplete = ProductionOrder::factory()->create(['unit_price' => 100000]);

    foreach (['C3', 'C4', 'C5'] as $code) {
        Assessment::factory()->create(['production_order_id' => $complete->id, 'criterion_id' => $criteria[$code]->id, 'value_updated_at' => now()]);
        Assessment::factory()->create(['production_order_id' => $stale->id, 'criterion_id' => $criteria[$code]->id, 'value_updated_at' => now()->subDays(9)]);
    }

    Livewire::test(AssessmentMatrix::class)
        ->filterTable('incomplete', true)
        ->assertCanSeeTableRecords([$incomplete])
        ->assertCanNotSeeTableRecords([$complete, $stale])
        ->removeTableFilter('incomplete')
        ->filterTable('stale', true)
        ->assertCanSeeTableRecords([$stale])
        ->assertCanNotSeeTableRecords([$complete, $incomplete]);
});

it('lets the manager save a manual value and records the history', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);
    $order = ProductionOrder::factory()->create();

    Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C3', $order), ['value' => 75])
        ->assertHasNoFormErrors();

    $assessment = Assessment::query()->where('production_order_id', $order->id)->firstOrFail();

    expect((float) $assessment->raw_value)->toBe(75.0)
        ->and($assessment->updated_by)->toBe($manager->id)
        ->and(AssessmentHistory::query()->where('assessment_id', $assessment->id)->count())->toBe(1);
});

it('refuses a percentage above 100 and a negative number of hours', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create();

    Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C3', $order), ['value' => 120])
        ->assertHasFormErrors(['value']);

    Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C4', $order), ['value' => -2])
        ->assertHasFormErrors(['value']);

    expect(Assessment::count())->toBe(0);
});

it('lets the supervisor update C4 and C5 only', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $this->actingAs($supervisor);
    $order = ProductionOrder::factory()->create();

    $page = Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C4', $order), ['value' => 30])
        ->callAction(editCell('C5', $order), ['value' => 40])
        ->assertActionHidden(editCell('C3', $order))
        ->assertActionHidden(editCell('C2', $order));

    expect(Assessment::query()->where('criterion_id', $this->criteria['C4']->id)->value('raw_value'))->toEqual('30.00')
        ->and(Assessment::query()->where('criterion_id', $this->criteria['C5']->id)->value('raw_value'))->toEqual('40.00')
        ->and(Assessment::query()->where('criterion_id', $this->criteria['C3']->id)->exists())->toBeFalse();
});

it('never makes automatic cells clickable', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create(['unit_price' => 100000]);

    Livewire::test(AssessmentMatrix::class)
        ->assertActionHidden(editCell('C1', $order))
        ->assertActionHidden(editCell('C7', $order))
        ->assertActionHidden(editCell('C6', $order))
        ->assertActionVisible(editCell('C3', $order))
        ->assertActionVisible(editCell('C2', $order));
});

it('opens the order value for editing only while the order has no unit price', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $unpriced = ProductionOrder::factory()->create(['unit_price' => null]);

    Livewire::test(AssessmentMatrix::class)
        ->assertActionVisible(editCell('C6', $unpriced))
        ->callAction(editCell('C6', $unpriced), ['value' => 80000000]);

    expect((float) Assessment::query()->where('production_order_id', $unpriced->id)->value('raw_value'))->toBe(80000000.0);
});

it('overrides the urgency of one order with a reason', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->for(Customer::factory()->urgency(2))->create();

    Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C2', $order), ['override' => true, 'value' => 5, 'reason' => 'Pelanggan strategis'])
        ->assertHasNoFormErrors()
        ->assertTableColumnStateSet(cell('C2'), '5', $order)
        ->assertTableColumnHasDescription(cell('C2'), 'Skala 5, ditimpa', $order);

    $assessment = Assessment::query()->where('production_order_id', $order->id)->firstOrFail();

    expect($assessment->is_overridden)->toBeTrue()
        ->and($assessment->override_reason)->toBe('Pelanggan strategis')
        ->and($order->customer->fresh()->urgency_level)->toBe(2);
});

it('requires a reason when overriding the urgency', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create();

    Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C2', $order), ['override' => true, 'value' => 5, 'reason' => ''])
        ->assertHasFormErrors(['reason']);

    expect(Assessment::count())->toBe(0);
});

it('goes back to the customer urgency when the override is switched off', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->for(Customer::factory()->urgency(2))->create();

    Livewire::test(AssessmentMatrix::class)
        ->callAction(editCell('C2', $order), ['override' => true, 'value' => 5, 'reason' => 'Sementara'])
        ->callAction(editCell('C2', $order), ['override' => false])
        ->assertTableColumnStateSet(cell('C2'), '2', $order)
        ->assertTableColumnHasDescription(cell('C2'), 'Skala 2, otomatis', $order);
});

it('hides the order value from the supervisor', function (): void {
    $order = ProductionOrder::factory()->create(['unit_price' => 100000]);

    $this->actingAs(User::factory()->supervisor()->create());

    Livewire::test(AssessmentMatrix::class)
        ->assertTableColumnExists(cell('C1'))
        ->assertTableColumnDoesNotExist(cell('C6'));

    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(AssessmentMatrix::class)
        ->assertTableColumnExists(cell('C6'));
});

it('searches orders by number and customer', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $target = ProductionOrder::factory()->for(Customer::factory()->create(['name' => 'PT Cari Aku']))->create();
    $other = ProductionOrder::factory()->create();

    Livewire::test(AssessmentMatrix::class)
        ->searchTable('Cari Aku')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('reads the assessments of all orders with a fixed number of queries', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    ProductionOrder::factory()->count(12)->create()->each(function (ProductionOrder $order): void {
        Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => $this->criteria['C3']->id]);
    });

    DB::enableQueryLog();

    Livewire::test(AssessmentMatrix::class)->assertOk();

    $assessmentQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `assessments`'))->count();

    expect($assessmentQueries)->toBeLessThanOrEqual(3);
});
