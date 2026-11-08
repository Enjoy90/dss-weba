<?php

use App\Services\Saw\SawCalculator;

function weights116(): array
{
    return ['C1' => 0.30, 'C2' => 0.20, 'C3' => 0.15, 'C4' => 0.15, 'C5' => 0.10, 'C6' => 0.10];
}

function matrix116(): array
{
    return [
        'A' => ['C1' => 5, 'C2' => 4, 'C3' => 5, 'C4' => 3, 'C5' => 4, 'C6' => 3],
        'B' => ['C1' => 3, 'C2' => 5, 'C3' => 2, 'C4' => 4, 'C5' => 2, 'C6' => 4],
        'C' => ['C1' => 4, 'C2' => 2, 'C3' => 4, 'C4' => 5, 'C5' => 5, 'C6' => 2],
    ];
}

it('reproduces the worked example of PRD 11.6', function (): void {
    $results = (new SawCalculator)->calculate(weights116(), matrix116());

    expect($results['A']->score)->toBe(0.855)
        ->and($results['B']->score)->toBe(0.7)
        ->and($results['C']->score)->toBe(0.74);

    $ranking = collect($results)->sortByDesc(fn ($result) => $result->score)->keys()->all();

    expect($ranking)->toBe(['A', 'C', 'B']);
});

it('normalizes each column by its own maximum', function (): void {
    $results = (new SawCalculator)->calculate(weights116(), matrix116());

    expect($results['A']->normalized)->toBe(['C1' => 1.0, 'C2' => 0.8, 'C3' => 1.0, 'C4' => 0.6, 'C5' => 0.8, 'C6' => 0.75])
        ->and($results['B']->normalized)->toBe(['C1' => 0.6, 'C2' => 1.0, 'C3' => 0.4, 'C4' => 0.8, 'C5' => 0.4, 'C6' => 1.0])
        ->and($results['C']->normalized)->toBe(['C1' => 0.8, 'C2' => 0.4, 'C3' => 0.8, 'C4' => 1.0, 'C5' => 1.0, 'C6' => 0.5]);
});

it('keeps the weighted contribution of every criterion', function (): void {
    $results = (new SawCalculator)->calculate(weights116(), matrix116());

    expect($results['A']->weighted)->toBe(['C1' => 0.3, 'C2' => 0.16, 'C3' => 0.15, 'C4' => 0.09, 'C5' => 0.08, 'C6' => 0.075])
        ->and(round(array_sum($results['A']->weighted), 6))->toBe($results['A']->score);
});

it('works with any number of criteria', function (): void {
    $results = (new SawCalculator)->calculate(
        ['X' => 0.6, 'Y' => 0.4],
        ['one' => ['X' => 5, 'Y' => 1], 'two' => ['X' => 1, 'Y' => 5]],
    );

    expect($results['one']->score)->toBe(0.68)
        ->and($results['two']->score)->toBe(0.52);

    $seven = array_combine(
        ['C1', 'C2', 'C3', 'C4', 'C5', 'C6', 'C7'],
        [0.27, 0.18, 0.135, 0.135, 0.09, 0.09, 0.10],
    );

    $results = (new SawCalculator)->calculate($seven, ['p' => array_fill_keys(array_keys($seven), 3)]);

    expect($results['p']->score)->toBe(1.0);
});

it('gives a single order the full weight because it is its own maximum', function (): void {
    $results = (new SawCalculator)->calculate(weights116(), ['only' => ['C1' => 2, 'C2' => 1, 'C3' => 3, 'C4' => 1, 'C5' => 2, 'C6' => 1]]);

    expect($results['only']->score)->toBe(1.0)
        ->and(array_unique($results['only']->normalized))->toBe(['C1' => 1.0]);
});

it('gives orders with identical scores the same result', function (): void {
    $row = ['C1' => 3, 'C2' => 3, 'C3' => 3, 'C4' => 3, 'C5' => 3, 'C6' => 3];
    $results = (new SawCalculator)->calculate(weights116(), ['a' => $row, 'b' => $row, 'c' => $row]);

    expect($results['a']->score)->toBe(1.0)
        ->and($results['b']->score)->toBe($results['a']->score)
        ->and($results['c']->score)->toBe($results['a']->score);
});

it('counts a missing score as the worst scale and reports it', function (): void {
    $scores = matrix116();
    $scores['B']['C3'] = null;

    $results = (new SawCalculator)->calculate(weights116(), $scores);

    // The C3 maximum stays 5, so B drops from 0.15 * 0.4 to 0.15 * 0.2.
    expect($results['B']->score)->toBe(0.67)
        ->and($results['B']->missing)->toBe(['C3'])
        ->and($results['A']->missing)->toBe([])
        ->and($results['A']->score)->toBe(0.855);
});

it('treats a criterion left out of the row like a missing score', function (): void {
    $scores = matrix116();
    unset($scores['C']['C5']);

    $results = (new SawCalculator)->calculate(weights116(), $scores);

    // C now scores 1 on C5, so the C5 maximum falls to 4 and the other orders gain a little.
    expect($results['C']->missing)->toBe(['C5'])
        ->and($results['C']->normalized['C5'])->toBe(0.25)
        ->and($results['A']->normalized['C5'])->toBe(1.0);
});

it('rounds the score to six decimals', function (): void {
    $results = (new SawCalculator)->calculate(
        ['X' => 1],
        ['a' => ['X' => 1], 'b' => ['X' => 3]],
    );

    expect($results['a']->score)->toBe(0.333333)
        ->and($results['b']->score)->toBe(1.0);
});

it('returns nothing when there are no orders', function (): void {
    expect((new SawCalculator)->calculate(weights116(), []))->toBe([]);
});

it('refuses to work without criteria or with a score below one', function (): void {
    expect(fn () => (new SawCalculator)->calculate([], ['a' => []]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new SawCalculator)->calculate(['X' => 1], ['a' => ['X' => 0]]))->toThrow(InvalidArgumentException::class);
});
