<?php

use App\Models\ActivityLog;
use App\Models\Criterion;
use App\Models\Setting;
use App\Services\CriterionWeights;
use Database\Seeders\CriteriaSeeder;

function weightsInOrder(): array
{
    return Criterion::query()->orderBy('id')->pluck('weight')->map(fn ($w): string => (string) $w)->all();
}

it('turns importance 5, 4, 3, 3, 2, 2 into the weights of the specification', function (): void {
    foreach ([5, 4, 3, 3, 2, 2] as $importance) {
        Criterion::factory()->importance($importance)->create();
    }

    expect(weightsInOrder())->toBe(['0.2631', '0.2105', '0.1579', '0.1579', '0.1053', '0.1053'])
        ->and(Criterion::query()->sum('weight'))->toEqual(1);
});

it('always totals exactly one, even when the division does not come out even', function (): void {
    foreach (range(1, 3) as $ignored) {
        Criterion::factory()->importance(3)->create();
    }

    $points = CriterionWeights::basisPointsFromImportance(Criterion::query()->get());

    expect(array_sum($points))->toBe(10000)
        ->and(CriterionWeights::summary()['total'])->toBe(10000);
});

it('gives a single criterion the whole weight', function (): void {
    $only = Criterion::factory()->importance(4)->create();

    expect((float) $only->fresh()->weight)->toBe(1.0);
});

it('recomputes the weights when a criterion is deactivated and zeroes the inactive one', function (): void {
    $a = Criterion::factory()->importance(3)->create();
    $b = Criterion::factory()->importance(3)->create();
    $c = Criterion::factory()->importance(3)->create();

    $c->update(['is_active' => false]);

    expect((string) $a->fresh()->weight)->toBe('0.5000')
        ->and((string) $b->fresh()->weight)->toBe('0.5000')
        ->and((string) $c->fresh()->weight)->toBe('0.0000');
});

it('recomputes the weights when an importance level changes', function (): void {
    $a = Criterion::factory()->importance(3)->create();
    $b = Criterion::factory()->importance(3)->create();

    $b->update(['importance' => 5]);

    expect((string) $a->fresh()->weight)->toBe('0.3750')
        ->and((string) $b->fresh()->weight)->toBe('0.6250');
});

it('keeps direct weights when importance changes or criteria are added', function (): void {
    $a = Criterion::factory()->importance(3)->create();
    $b = Criterion::factory()->importance(3)->create();

    CriterionWeights::setDirect([$a->id => 70, $b->id => 30]);

    $a->update(['importance' => 1]);
    Criterion::factory()->importance(5)->create();

    expect(CriterionWeights::mode())->toBe('direct')
        ->and((string) $a->fresh()->weight)->toBe('0.7000')
        ->and((string) $b->fresh()->weight)->toBe('0.3000');
});

it('stores direct percentages with two decimals without drifting from one', function (): void {
    $criteria = Criterion::factory()->count(3)->create();

    CriterionWeights::setDirect([
        $criteria[0]->id => 33.33,
        $criteria[1]->id => 33.33,
        $criteria[2]->id => 33.34,
    ]);

    expect(CriterionWeights::summary()['total'])->toBe(10000);
});

it('returns to importance based weights on request', function (): void {
    $a = Criterion::factory()->importance(3)->create();
    $b = Criterion::factory()->importance(1)->create();

    CriterionWeights::setDirect([$a->id => 50, $b->id => 50]);
    CriterionWeights::useImportance();

    expect(CriterionWeights::mode())->toBe('importance')
        ->and((string) $a->fresh()->weight)->toBe('0.7500')
        ->and((string) $b->fresh()->weight)->toBe('0.2500');
});

it('reports not ready with fewer than two active criteria', function (): void {
    expect(CriterionWeights::summary()['ready'])->toBeFalse();

    Criterion::factory()->create();

    $summary = CriterionWeights::summary();

    expect($summary['ready'])->toBeFalse()
        ->and($summary['message'])->toContain('minimal dua kriteria');
});

it('reports not ready when direct weights do not total one', function (): void {
    $a = Criterion::factory()->create();
    $b = Criterion::factory()->create();

    CriterionWeights::setDirect([$a->id => 60, $b->id => 30]);

    $summary = CriterionWeights::summary();

    expect($summary['ready'])->toBeFalse()
        ->and($summary['total'])->toBe(9000)
        ->and($summary['message'])->toContain('100%');
});

it('treats an unknown mode value as importance', function (): void {
    Setting::put('weight_mode', 'sesuatu-yang-aneh');

    expect(CriterionWeights::mode())->toBe('importance');
});

it('writes one history entry per changed weight with the old and the new value', function (): void {
    $a = Criterion::factory()->importance(3)->create();
    $b = Criterion::factory()->importance(3)->create();

    $before = ActivityLog::count();

    Criterion::factory()->importance(3)->create();

    $entry = $a->activities()->where('action', 'criterion.updated')->first();

    expect(ActivityLog::count())->toBeGreaterThan($before)
        ->and($entry->changeLines())->toBe(['Bobot: 0,5000 menjadi 0,3334'])
        ->and($b->activities()->where('action', 'criterion.updated')->count())->toBe(2);
});

it('does not write a history entry when a recompute changes nothing', function (): void {
    $a = Criterion::factory()->importance(3)->create();
    Criterion::factory()->importance(3)->create();

    $count = $a->activities()->count();

    CriterionWeights::refresh();

    expect($a->activities()->count())->toBe($count);
});

it('is not ready while an active criterion has no valid scale and says which one', function (): void {
    $this->seed(CriteriaSeeder::class);

    expect(CriterionWeights::summary()['ready'])->toBeTrue();

    $new = Criterion::factory()->create(['code' => 'C8']);
    CriterionWeights::useImportance();

    $summary = CriterionWeights::summary();

    expect($summary['ready'])->toBeFalse()
        ->and($summary['total'])->toBe(10000)
        ->and($summary['missing_scales'])->toBe(['C8'])
        ->and($summary['message'])->toContain('skala nilai C8');

    $new->update(['is_active' => false]);

    expect(CriterionWeights::summary()['ready'])->toBeTrue();
});

it('is not ready when a saved scale runs against the direction of the criterion', function (): void {
    $this->seed(CriteriaSeeder::class);

    $c1 = Criterion::query()->where('code', 'C1')->firstOrFail();
    $c1->update(['direction' => 'higher_first']);

    expect(CriterionWeights::summary()['ready'])->toBeFalse()
        ->and(CriterionWeights::summary()['missing_scales'])->toBe(['C1']);
});
