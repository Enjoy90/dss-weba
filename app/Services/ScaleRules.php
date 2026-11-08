<?php

namespace App\Services;

use App\Enums\CriterionDirection;
use App\Models\CriterionScale;
use Illuminate\Support\Collection;

/**
 * Validation and conversion of the 1 to 5 scale of a criterion.
 * A range includes its lower bound and excludes its upper bound.
 * An empty bound means unlimited, so every possible value falls in exactly one range.
 */
class ScaleRules
{
    /**
     * @param  Collection<int, CriterionScale>  $scales
     * @return array<int, array{score: int, lower: ?float, upper: ?float}>
     */
    public static function rowsFromScales(Collection $scales): array
    {
        return $scales->map(fn (CriterionScale $scale): array => [
            'score' => $scale->score,
            'lower' => $scale->lower_bound === null ? null : (float) $scale->lower_bound,
            'upper' => $scale->upper_bound === null ? null : (float) $scale->upper_bound,
        ])->values()->all();
    }

    /**
     * @param  array<int, array{score: int, lower: ?float, upper: ?float}>  $rows
     */
    public static function convert(array $rows, float $value): ?int
    {
        foreach ($rows as $row) {
            $aboveLower = $row['lower'] === null || $value >= $row['lower'];
            $belowUpper = $row['upper'] === null || $value < $row['upper'];

            if ($aboveLower && $belowUpper) {
                return $row['score'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{score: int, lower: ?float, upper: ?float}>  $rows
     * @return array<int, string> error messages, empty when the scale is valid
     */
    public static function validate(array $rows, CriterionDirection $direction): array
    {
        $scores = array_column($rows, 'score');
        sort($scores);

        if ($scores !== [1, 2, 3, 4, 5]) {
            return ['Skala harus terdiri dari lima baris, yaitu skala 1 sampai 5.'];
        }

        $errors = [];

        foreach ($rows as $row) {
            if ($row['lower'] !== null && $row['upper'] !== null && $row['lower'] >= $row['upper']) {
                $errors[] = "Skala {$row['score']}: batas bawah harus lebih kecil dari batas atas.";
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        usort($rows, fn (array $a, array $b): int => ($a['lower'] ?? -INF) <=> ($b['lower'] ?? -INF));

        if ($rows[0]['lower'] !== null) {
            $errors[] = "Rentang paling bawah (skala {$rows[0]['score']}) harus tanpa batas bawah supaya nilai sekecil apa pun tetap punya skala. Kosongkan batas bawahnya.";
        }

        $last = $rows[count($rows) - 1];

        if ($last['upper'] !== null) {
            $errors[] = "Rentang paling atas (skala {$last['score']}) harus tanpa batas atas supaya nilai sebesar apa pun tetap punya skala. Kosongkan batas atasnya.";
        }

        for ($i = 1; $i < count($rows); $i++) {
            $previous = $rows[$i - 1];
            $current = $rows[$i];

            if ($previous['upper'] === null || $current['lower'] === null || $current['lower'] < $previous['upper']) {
                $errors[] = "Rentang skala {$previous['score']} dan skala {$current['score']} tumpang tindih.";
            } elseif ($current['lower'] > $previous['upper']) {
                $errors[] = 'Ada celah antara skala '.$previous['score'].' dan skala '.$current['score'].': nilai dari '
                    .self::number($previous['upper']).' sampai kurang dari '.self::number($current['lower']).' tidak punya skala.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $order = array_column($rows, 'score');
        $expected = $direction === CriterionDirection::LowerFirst ? [5, 4, 3, 2, 1] : [1, 2, 3, 4, 5];

        if ($order !== $expected) {
            return [$direction === CriterionDirection::LowerFirst
                ? 'Arah nilai "makin kecil didahulukan": skala 5 harus berada di rentang paling kecil dan skala 1 di rentang paling besar.'
                : 'Arah nilai "makin besar didahulukan": skala 5 harus berada di rentang paling besar dan skala 1 di rentang paling kecil.'];
        }

        return [];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
