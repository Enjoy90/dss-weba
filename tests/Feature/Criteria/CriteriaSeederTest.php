<?php

use App\Models\Criterion;
use App\Services\CriterionWeights;
use App\Services\ScaleRules;
use Database\Seeders\CriteriaSeeder;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
});

it('seeds the seven starting criteria with the proposed weights totalling exactly one', function (): void {
    $weights = Criterion::query()->orderBy('code')->pluck('weight', 'code')->map(fn ($w): string => (string) $w)->all();

    expect($weights)->toBe([
        'C1' => '0.2700', 'C2' => '0.1800', 'C3' => '0.1350', 'C4' => '0.1350',
        'C5' => '0.0900', 'C6' => '0.0900', 'C7' => '0.1000',
    ])
        ->and(CriterionWeights::summary()['total'])->toBe(10000)
        ->and(CriterionWeights::summary()['ready'])->toBeTrue()
        ->and(CriterionWeights::mode())->toBe('direct');
});

it('can be seeded twice without duplicating anything', function (): void {
    $this->seed(CriteriaSeeder::class);

    expect(Criterion::count())->toBe(7)
        ->and(Criterion::query()->withCount('scales')->get()->every(fn (Criterion $c): bool => $c->scales_count === 5))->toBeTrue()
        ->and(CriterionWeights::summary()['total'])->toBe(10000);
});

it('gives every criterion a valid five row scale that matches its direction', function (): void {
    foreach (Criterion::query()->with('scales')->get() as $criterion) {
        $errors = ScaleRules::validate(ScaleRules::rowsFromScales($criterion->scales), $criterion->direction);

        expect($errors)->toBe([], "Skala {$criterion->code} tidak valid");
    }
});

it('converts the examples of the specification with the saved scales', function (): void {
    $c1 = Criterion::query()->where('code', 'C1')->firstOrFail();
    $c6 = Criterion::query()->where('code', 'C6')->firstOrFail();
    $c7 = Criterion::query()->where('code', 'C7')->firstOrFail();

    expect($c1->scoreFor(0))->toBe(5)
        ->and($c1->scoreFor(2))->toBe(4)
        ->and($c1->scoreFor(20))->toBe(1)
        ->and($c1->scoreFor(-3))->toBe(5)
        ->and($c6->scoreFor(100000000))->toBe(5)
        ->and($c6->scoreFor(99999999.99))->toBe(4)
        ->and($c7->scoreFor(0))->toBe(1)
        ->and($c7->scoreFor(7))->toBe(5)
        ->and($c7->scoreFor(8))->toBe(5)
        ->and($c7->scoreFor(6))->toBe(4);
});

it('marks which criteria are filled automatically and which by hand', function (): void {
    $sources = Criterion::query()->orderBy('code')->get()->mapWithKeys(fn (Criterion $c): array => [$c->code => $c->value_source->value])->all();

    expect($sources)->toBe([
        'C1' => 'deadline', 'C2' => 'customer_urgency', 'C3' => 'manual', 'C4' => 'manual',
        'C5' => 'manual', 'C6' => 'order_value', 'C7' => 'waiting',
    ]);
});

it('gives the weights shown in the user guide when switching the seeded criteria to importance', function (): void {
    CriterionWeights::useImportance();

    $weights = fn (): array => Criterion::query()->orderBy('code')->pluck('weight')->map(fn ($w): string => (string) $w)->all();

    expect($weights())->toBe(['0.2272', '0.1818', '0.1364', '0.1364', '0.0909', '0.0909', '0.1364']);

    Criterion::query()->where('code', 'C5')->firstOrFail()->update(['importance' => 5]);

    expect($weights())->toBe(['0.2000', '0.1600', '0.1200', '0.1200', '0.2000', '0.0800', '0.1200'])
        ->and(CriterionWeights::summary()['total'])->toBe(10000);
});
