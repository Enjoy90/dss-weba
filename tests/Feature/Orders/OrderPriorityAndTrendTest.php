<?php

use App\Enums\EvaluationStatus;
use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\Pages\ViewProductionOrder;
use App\Filament\Widgets\OrderRankTrendChart;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\OrderLocker;
use Database\Seeders\CriteriaSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->admin = User::factory()->admin()->create();
});

function trendSetup(): void
{
    test()->seed(CriteriaSeeder::class);
    test()->criteria = Criterion::query()->get()->keyBy('code');
}

function trendOrder(int $dueInDays): ProductionOrder
{
    $order = ProductionOrder::factory()->dueInDays($dueInDays)->create(['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100]);

    foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
        Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => test()->criteria[$code]->id, 'raw_value' => $value, 'value_updated_at' => now()]);
    }

    return $order;
}

it('shows the Prioritas Utama label in the status cell of the order list and filters on it', function (): void {
    $top = ProductionOrder::factory()->create(['priority' => OrderPriority::Top]);
    $plain = ProductionOrder::factory()->create();

    $this->actingAs($this->supervisor);

    Livewire::test(ListProductionOrders::class)
        ->assertCanSeeTableRecords([$top, $plain])
        ->assertTableColumnStateSet('status', ['Antri', 'Prioritas Utama'], $top)
        ->assertTableColumnStateSet('status', ['Antri'], $plain)
        ->filterTable('priority', OrderPriority::Top->value)
        ->assertCanSeeTableRecords([$top])
        ->assertCanNotSeeTableRecords([$plain]);
});

it('offers the lock buttons only to the manager and the leader', function (): void {
    $order = ProductionOrder::factory()->create();
    $locked = ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Penting', 'lock_until' => null]);

    foreach ([$this->manager, $this->leader] as $user) {
        $this->actingAs($user);

        Livewire::test(ListProductionOrders::class)
            ->assertActionVisible(TestAction::make('lockOrder')->table($order))
            ->assertActionHidden(TestAction::make('unlockOrder')->table($order))
            ->assertActionHidden(TestAction::make('lockOrder')->table($locked))
            ->assertActionVisible(TestAction::make('unlockOrder')->table($locked));
    }

    foreach ([$this->admin, $this->supervisor] as $user) {
        $this->actingAs($user);

        Livewire::test(ListProductionOrders::class)
            ->assertActionHidden(TestAction::make('lockOrder')->table($order))
            ->assertActionHidden(TestAction::make('unlockOrder')->table($locked));
    }
});

it('does not offer a manual lock on an order being worked on or finished', function (): void {
    $working = ProductionOrder::factory()->inProgress()->create();
    $done = ProductionOrder::factory()->status(OrderStatus::Done)->create();

    $this->actingAs($this->manager);

    Livewire::test(ListProductionOrders::class)
        ->assertActionHidden(TestAction::make('lockOrder')->table($working))
        ->assertActionHidden(TestAction::make('lockOrder')->table($done));
});

it('locks an order from the list with a reason and refuses without one (PRD F18 acceptance 3)', function (): void {
    $order = ProductionOrder::factory()->create();

    $this->actingAs($this->manager);

    Livewire::test(ListProductionOrders::class)
        ->callAction(TestAction::make('lockOrder')->table($order), ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($order->fresh()->lock_type)->toBe(LockType::None);

    Livewire::test(ListProductionOrders::class)
        ->callAction(TestAction::make('lockOrder')->table($order), ['reason' => 'Pelanggan penting', 'until' => today()->addDays(4)->toDateString()])
        ->assertHasNoActionErrors()
        ->assertNotified($order->number.' dikunci.');

    $fresh = $order->fresh();

    expect($fresh->lock_type)->toBe(LockType::Manual)
        ->and($fresh->lock_reason)->toBe('Pelanggan penting')
        ->and($fresh->locked_by)->toBe($this->manager->id)
        ->and($fresh->lock_until->toDateString())->toBe(today()->addDays(4)->toDateString());
});

it('releases a manual lock from the list with a reason', function (): void {
    $order = ProductionOrder::factory()->create();
    app(OrderLocker::class)->lock($order, $this->manager, 'Pasang');

    $this->actingAs($this->leader);

    Livewire::test(ListProductionOrders::class)
        ->callAction(TestAction::make('unlockOrder')->table($order->fresh()), ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ListProductionOrders::class)
        ->callAction(TestAction::make('unlockOrder')->table($order->fresh()), ['reason' => 'Sudah selesai urusannya'])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($order->fresh()->lock_type)->toBe(LockType::None);
});

it('shows the lock buttons and the trend section on the order page', function (): void {
    $order = ProductionOrder::factory()->create();

    $this->actingAs($this->manager);

    Livewire::test(ViewProductionOrder::class, ['record' => $order->id])
        ->assertActionVisible('lockOrder')
        ->assertActionHidden('unlockOrder')
        ->assertSeeLivewire(OrderRankTrendChart::class);

    Livewire::test(OrderRankTrendChart::class, ['record' => $order])
        ->assertSee('Tren peringkat')
        ->assertSee('Belum ada evaluasi yang memuat order ini');

    $this->actingAs($this->supervisor);

    Livewire::test(ViewProductionOrder::class, ['record' => $order->id])
        ->assertActionHidden('lockOrder')
        ->assertSeeLivewire(OrderRankTrendChart::class);
});

it('draws the rank of an order in every evaluation, oldest first, without canceled ones', function (): void {
    trendSetup();
    $target = trendOrder(10);
    trendOrder(20);

    $this->travelTo(now()->setTime(8, 0));
    $one = app(EvaluationService::class)->run($this->manager);
    $this->travelTo(now()->setTime(9, 0));
    $canceled = app(EvaluationService::class)->run($this->manager);
    $canceled->update(['status' => EvaluationStatus::Canceled]);

    trendOrder(1);
    $this->travelTo(now()->setTime(10, 0));
    $three = app(EvaluationService::class)->run($this->manager);

    $chart = Livewire::test(OrderRankTrendChart::class, ['record' => $target])->instance();
    $points = $chart->points();

    expect($points)->toHaveCount(2)
        ->and(array_column($points, 'rank'))->toBe([
            $one->items()->where('production_order_id', $target->id)->value('final_rank'),
            $three->items()->where('production_order_id', $target->id)->value('final_rank'),
        ])
        ->and($points[0]['label'])->toBe(today()->format('d/m').' 08:00')
        ->and($points[1]['total'])->toBe(3)
        ->and($chart->getDescription())->toContain('peringkat '.$points[1]['rank'].' dari 3')
        ->and($chart->getDescription())->toContain('Garis yang naik berarti order naik di daftar');

    $data = (new ReflectionMethod($chart, 'getData'))->invoke($chart);
    $options = (new ReflectionMethod($chart, 'getOptions'))->invoke($chart);

    expect($data['datasets'][0]['data'])->toBe(array_column($points, 'rank'))
        ->and($data['labels'])->toBe(array_column($points, 'label'))
        ->and($options['scales']['y']['reverse'])->toBeTrue();
});

it('draws the trend on the page of an order that was evaluated', function (): void {
    trendSetup();
    $order = trendOrder(5);
    app(EvaluationService::class)->run($this->manager);

    $this->actingAs($this->leader);

    Livewire::test(ViewProductionOrder::class, ['record' => $order->id])
        ->assertSeeLivewire(OrderRankTrendChart::class);

    Livewire::test(OrderRankTrendChart::class, ['record' => $order])
        ->assertSee('Tren peringkat')
        ->assertDontSee('Belum ada evaluasi yang memuat order ini')
        ->assertSee('Perhitungan terakhir: peringkat 1 dari 1');
});
