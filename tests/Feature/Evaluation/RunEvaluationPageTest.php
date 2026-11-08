<?php

use App\Enums\LockType;
use App\Filament\Pages\RunEvaluation;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\User;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
});

function pageOrder(?ProductionOrder $order = null, bool $fillManual = true): ProductionOrder
{
    $order ??= ProductionOrder::factory()->create();

    if ($fillManual) {
        foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
            Assessment::factory()->create([
                'production_order_id' => $order->id,
                'criterion_id' => test()->criteria[$code]->id,
                'raw_value' => $value,
                'value_updated_at' => now(),
            ]);
        }
    }

    return $order;
}

it('opens for the manager only', function (): void {
    foreach (['admin', 'supervisor', 'leader'] as $role) {
        $this->actingAs(User::factory()->$role()->create());

        expect(RunEvaluation::canAccess())->toBeFalse();
        $this->get(RunEvaluation::getUrl())->assertForbidden();
    }

    $this->actingAs(User::factory()->manager()->create());

    expect(RunEvaluation::canAccess())->toBeTrue();
    $this->get(RunEvaluation::getUrl())->assertOk()->assertSee('Jalankan Perhitungan');
});

it('redirects a guest to the login page', function (): void {
    $this->get(RunEvaluation::getUrl())->assertRedirect();
});

it('shows the empty state and explains why it cannot run when there are no orders', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(RunEvaluation::class)
        ->assertSee('Perhitungan belum bisa dijalankan')
        ->assertSee('Belum ada order berstatus Antri atau Dikerjakan')
        ->assertSee('Belum ada hasil perhitungan')
        ->assertActionDisabled('run');
});

it('shows the readiness counts before anything is calculated', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder();
    pageOrder();
    pageOrder(fillManual: false);

    Livewire::test(RunEvaluation::class)
        ->assertSee('Data siap dihitung')
        ->assertSee('Order yang dihitung')
        ->assertSee('Belum lengkap')
        ->assertSee('Order yang belum lengkap tetap dihitung')
        ->assertActionEnabled('run')
        ->assertSee('Belum ada hasil perhitungan');
});

it('runs a calculation and lists the ranking afterwards', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $near = pageOrder(ProductionOrder::factory()->dueInDays(1)->create());
    $far = pageOrder(ProductionOrder::factory()->dueInDays(25)->create());

    Livewire::test(RunEvaluation::class)
        ->callAction('run', ['name' => 'Uji halaman'])
        ->assertHasNoActionErrors()
        ->assertNotified('Perhitungan selesai')
        ->assertSee('Hasil terakhir: Uji halaman')
        ->assertSee($near->number)
        ->assertSee($far->number)
        ->assertDontSee('Baru')
        ->assertSee('Biasa');

    $evaluation = Evaluation::query()->sole();

    expect($evaluation->name)->toBe('Uji halaman')
        ->and($evaluation->evaluated_by)->toBe($manager->id)
        ->and($evaluation->order_count)->toBe(2);
});

it('lists the ranking in final rank order with the system rank beside it', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    pageOrder(ProductionOrder::factory()->dueInDays(0)->create());
    $working = pageOrder(ProductionOrder::factory()->inProgress()->dueInDays(25)->create());

    $component = Livewire::test(RunEvaluation::class)->callAction('run');

    $items = EvaluationItem::query()->orderBy('final_rank')->get();

    $component->assertCanSeeTableRecords($items, inOrder: true);

    expect($items->first()->production_order_id)->toBe($working->id)
        ->and($items->first()->rank_group->label())->toBe('Terkunci');
});

it('asks to confirm incomplete orders and saves nothing without the tick', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder();
    $incomplete = pageOrder(fillManual: false);

    $component = Livewire::test(RunEvaluation::class)->mountAction('run');

    $text = (string) $component->instance()->getMountedAction()->getModalDescription();

    expect($text)->toContain('2 order akan dihitung dengan 7 kriteria aktif')
        ->and($text)->toContain('Belum lengkap (kriteria yang kosong dihitung sebagai skala 1)')
        ->and($text)->toContain($incomplete->number.': C3, C4, C5')
        ->and($text)->not->toContain('Perlu diperbarui');

    $component->callMountedAction()->assertHasActionErrors(['confirm' => 'accepted']);

    expect(Evaluation::query()->count())->toBe(0);

    Livewire::test(RunEvaluation::class)
        ->callAction('run', ['confirm' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Perhitungan selesai');

    expect(Evaluation::query()->sole()->order_count)->toBe(2);
});

it('does not ask for confirmation when every order is complete', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder();

    Livewire::test(RunEvaluation::class)
        ->callAction('run', [])
        ->assertHasNoActionErrors()
        ->assertNotified('Perhitungan selesai');
});

it('explains a blocked calculation instead of failing', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder();
    $this->criteria['C1']->update(['weight' => 0.5]);

    Livewire::test(RunEvaluation::class)
        ->assertSee('total bobot harus tepat 100%')
        ->assertActionDisabled('run');

    expect(Evaluation::query()->count())->toBe(0);
});

it('rejects a name or a note that is too long', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder();

    Livewire::test(RunEvaluation::class)
        ->callAction('run', ['name' => str_repeat('a', 151), 'notes' => str_repeat('b', 501)])
        ->assertHasActionErrors(['name' => 'max', 'notes' => 'max']);

    expect(Evaluation::query()->count())->toBe(0);
});

it('marks a second calculation with how far each order moved', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder(ProductionOrder::factory()->dueInDays(3)->create());
    pageOrder(ProductionOrder::factory()->dueInDays(20)->create());

    Livewire::test(RunEvaluation::class)->callAction('run');

    $surprise = pageOrder(ProductionOrder::factory()->dueInDays(0)->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Penting']));

    Livewire::test(RunEvaluation::class)
        ->callAction('run')
        ->assertSee('Turun 1')
        ->assertSee('Baru')
        ->assertSee($surprise->number);
});

it('shows no rupiah figure anywhere on the page', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    pageOrder(ProductionOrder::factory()->create(['unit_price' => 987654, 'quantity' => 3]));

    Livewire::test(RunEvaluation::class)
        ->callAction('run')
        ->assertDontSee('987.654')
        ->assertDontSee('2.962.962');
});

it('lists stale orders in the dialog and shortens a long list', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $stale = pageOrder();
    Assessment::query()->where('production_order_id', $stale->id)->update(['value_updated_at' => now()->subDays(6)]);

    foreach (range(1, 10) as $ignored) {
        pageOrder(fillManual: false);
    }

    $component = Livewire::test(RunEvaluation::class)->mountAction('run');
    $text = (string) $component->instance()->getMountedAction()->getModalDescription();

    expect($text)->toContain('Perlu diperbarui (nilai manual sudah lama)')
        ->and($text)->toContain($stale->number.': C3, C4, C5')
        ->and($text)->toContain('Dan 2 order lainnya.')
        ->and(substr_count($text, '<li>'))->toBe(8 + 1 + 1);
});
