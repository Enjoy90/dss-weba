<?php

use App\Enums\CriterionDirection;
use App\Services\ScaleRules;

/**
 * @param  array<int, array{0: int, 1: ?float, 2: ?float}>  $triples  score, lower, upper
 * @return array<int, array{score: int, lower: ?float, upper: ?float}>
 */
function scaleRows(array $triples): array
{
    return array_map(fn (array $t): array => ['score' => $t[0], 'lower' => $t[1], 'upper' => $t[2]], $triples);
}

function deadlineScale(): array
{
    return scaleRows([[5, null, 2], [4, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]);
}

function risingScale(): array
{
    return scaleRows([[1, null, 25], [2, 25, 50], [3, 50, 75], [4, 75, 100], [5, 100, null]]);
}

it('accepts a complete scale for a criterion where smaller is first', function (): void {
    expect(ScaleRules::validate(deadlineScale(), CriterionDirection::LowerFirst))->toBe([]);
});

it('accepts a complete scale for a criterion where bigger is first', function (): void {
    expect(ScaleRules::validate(risingScale(), CriterionDirection::HigherFirst))->toBe([]);
});

it('accepts rows given in any order', function (): void {
    $shuffled = array_reverse(deadlineScale());

    expect(ScaleRules::validate($shuffled, CriterionDirection::LowerFirst))->toBe([]);
});

it('rejects overlapping ranges', function (): void {
    $rows = scaleRows([[5, null, 3], [4, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]);

    $errors = ScaleRules::validate($rows, CriterionDirection::LowerFirst);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('tumpang tindih');
});

it('rejects a gap between ranges and says which values are left out', function (): void {
    $rows = scaleRows([[5, null, 2], [4, 3, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]);

    $errors = ScaleRules::validate($rows, CriterionDirection::LowerFirst);

    expect($errors[0])->toContain('celah')->toContain('2 sampai kurang dari 3');
});

it('requires the lowest range to have no lower bound and the highest no upper bound', function (): void {
    $noOpenBottom = scaleRows([[5, 0, 2], [4, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]);
    $noOpenTop = scaleRows([[5, null, 2], [4, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, 99]]);

    expect(ScaleRules::validate($noOpenBottom, CriterionDirection::LowerFirst)[0])->toContain('tanpa batas bawah')
        ->and(ScaleRules::validate($noOpenTop, CriterionDirection::LowerFirst)[0])->toContain('tanpa batas atas');
});

it('rejects a lower bound that is not below the upper bound', function (): void {
    $rows = scaleRows([[5, null, 2], [4, 4, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]);

    expect(ScaleRules::validate($rows, CriterionDirection::LowerFirst)[0])->toContain('Skala 4')->toContain('lebih kecil');
});

it('rejects a scale that runs against the direction of the criterion', function (): void {
    expect(ScaleRules::validate(deadlineScale(), CriterionDirection::HigherFirst)[0])->toContain('makin besar didahulukan')
        ->and(ScaleRules::validate(risingScale(), CriterionDirection::LowerFirst)[0])->toContain('makin kecil didahulukan');
});

it('rejects a scale that does not have exactly five rows', function (): void {
    $four = scaleRows([[5, null, 2], [4, 2, 4], [3, 4, 8], [2, 8, null]]);
    $duplicate = scaleRows([[5, null, 2], [5, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]);

    expect(ScaleRules::validate($four, CriterionDirection::LowerFirst)[0])->toContain('lima baris')
        ->and(ScaleRules::validate($duplicate, CriterionDirection::LowerFirst)[0])->toContain('lima baris');
});

it('converts the examples of the specification for the deadline criterion', function (): void {
    $rows = deadlineScale();

    expect(ScaleRules::convert($rows, 0))->toBe(5)
        ->and(ScaleRules::convert($rows, 2))->toBe(4)
        ->and(ScaleRules::convert($rows, 20))->toBe(1);
});

it('includes the lower bound and excludes the upper bound', function (): void {
    $rows = deadlineScale();

    expect(ScaleRules::convert($rows, 1.99))->toBe(5)
        ->and(ScaleRules::convert($rows, 2.0))->toBe(4)
        ->and(ScaleRules::convert($rows, 7.99))->toBe(3)
        ->and(ScaleRules::convert($rows, 8.0))->toBe(2);
});

it('puts a decimal value such as 79.5 percent in exactly one range', function (): void {
    $rows = risingScale();

    expect(ScaleRules::convert($rows, 79.5))->toBe(4)
        ->and(ScaleRules::convert($rows, 80))->toBe(4)
        ->and(ScaleRules::convert($rows, 100))->toBe(5);
});

it('handles values far outside the usual range and negative values', function (): void {
    expect(ScaleRules::convert(deadlineScale(), -12))->toBe(5)
        ->and(ScaleRules::convert(deadlineScale(), 9999))->toBe(1);
});

it('returns nothing when the ranges do not cover the value', function (): void {
    $rows = scaleRows([[5, 10, 20], [4, 20, 30], [3, 30, 40], [2, 40, 50], [1, 50, 60]]);

    expect(ScaleRules::convert($rows, 5))->toBeNull();
});
