<?php

use App\Enums\Completeness;
use App\Filament\Pages\AssessmentMatrix;
use App\Models\Assessment;
use App\Models\AssessmentHistory;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentResolver;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(DatabaseSeeder::class);
});

it('fills the open demo orders with manual values and only for manual criteria', function (): void {
    $codes = Assessment::query()->with('criterion')->get()->pluck('criterion.code')->unique()->sort()->values()->all();

    expect($codes)->toBe(['C2', 'C3', 'C4', 'C5'])
        ->and(Assessment::count())->toBe(57);
});

it('leaves automatic criteria out of the stored data', function (): void {
    $automatic = Criterion::query()->whereIn('code', ['C1', 'C6', 'C7'])->pluck('id');

    expect(Assessment::query()->whereIn('criterion_id', $automatic)->count())->toBe(0);
});

it('covers complete, incomplete and stale orders so every marker shows in the demo', function (): void {
    $resolver = AssessmentResolver::make();
    $criteria = Criterion::query()->active()->ordered()->with('scales')->get();

    $states = ProductionOrder::query()->open()->with(['customer', 'assessments'])->get()
        ->map(fn (ProductionOrder $order): Completeness => $resolver->completeness($resolver->resolveAll($order, $criteria)))
        ->countBy(fn (Completeness $state): string => $state->value);

    expect($states['incomplete'])->toBeGreaterThanOrEqual(3)
        ->and($states['stale'])->toBeGreaterThanOrEqual(3)
        ->and($states['complete'])->toBeGreaterThanOrEqual(10)
        ->and($states->sum())->toBe(21);
});

it('includes urgency overrides with their reasons and some value history', function (): void {
    $overrides = Assessment::query()->where('is_overridden', true)->get();

    expect($overrides)->toHaveCount(2)
        ->and($overrides->every(fn (Assessment $assessment): bool => filled($assessment->override_reason)))->toBeTrue()
        ->and(AssessmentHistory::count())->toBeGreaterThan(Assessment::count() - 1);
});

it('has an order that was never assessed and one with a single empty value', function (): void {
    $year = today()->format('Y');
    $c4 = Criterion::query()->where('code', 'C4')->firstOrFail();

    $never = ProductionOrder::query()->where('number', "ORD-{$year}-0018")->firstOrFail();
    $blank = ProductionOrder::query()->where('number', "ORD-{$year}-0013")->firstOrFail();

    expect(Assessment::query()->where('production_order_id', $never->id)->count())->toBe(0)
        ->and(Assessment::query()->where('production_order_id', $blank->id)->where('criterion_id', $c4->id)->exists())->toBeFalse()
        ->and(Assessment::query()->where('production_order_id', $blank->id)->count())->toBe(2);
});

it('shows the matrix with data for the manager and the supervisor', function (): void {
    foreach (['manajer@spk.test', 'supervisor@spk.test'] as $email) {
        $this->actingAs(User::query()->where('email', $email)->firstOrFail());

        Livewire::test(AssessmentMatrix::class)
            ->assertOk()
            ->assertCountTableRecords(21)
            ->assertSee('Belum diisi')
            ->assertSee('perlu diperbarui');
    }
});

it('filters the demo data down to the incomplete and the stale orders', function (): void {
    $this->actingAs(User::query()->where('email', 'manajer@spk.test')->firstOrFail());

    Livewire::test(AssessmentMatrix::class)
        ->filterTable('incomplete', true)
        ->assertCountTableRecords(4)
        ->removeTableFilter('incomplete')
        ->filterTable('stale', true)
        ->assertCountTableRecords(4);
});
