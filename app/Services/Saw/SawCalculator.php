<?php

namespace App\Services\Saw;

use InvalidArgumentException;

/**
 * Simple Additive Weighting without any database access (PRD chapter 11).
 *
 * Every score is already on the 1 to 5 scale where a higher number means "do this first", so each
 * column is a benefit column: r = x / max(x) and V = sum of (w * r). It works for any number of criteria.
 */
class SawCalculator
{
    public const DECIMALS = 6;

    /**
     * @param  array<string, float|int>  $weights  weight per criterion key
     * @param  array<int|string, array<string, int|null>>  $scores  criterion scores per order key; a missing score counts as 1
     * @return array<int|string, SawResult> keyed like $scores
     */
    public function calculate(array $weights, array $scores): array
    {
        if ($weights === []) {
            throw new InvalidArgumentException('At least one criterion is required.');
        }

        $matrix = [];
        $missing = [];

        foreach ($scores as $order => $row) {
            foreach (array_keys($weights) as $criterion) {
                $score = $row[$criterion] ?? null;

                if ($score === null) {
                    $missing[$order][] = (string) $criterion;
                    $score = 1;
                }

                if ($score < 1) {
                    throw new InvalidArgumentException("Score for criterion {$criterion} must be at least 1.");
                }

                $matrix[$order][$criterion] = $score;
            }
        }

        $max = [];

        foreach (array_keys($weights) as $criterion) {
            $max[$criterion] = $matrix === [] ? 1 : max(array_column($matrix, $criterion));
        }

        $results = [];

        foreach ($matrix as $order => $row) {
            $normalized = [];
            $weighted = [];
            $total = 0.0;

            foreach ($row as $criterion => $score) {
                $ratio = $score / $max[$criterion];
                $contribution = (float) $weights[$criterion] * $ratio;

                $normalized[$criterion] = round($ratio, self::DECIMALS);
                $weighted[$criterion] = round($contribution, self::DECIMALS);
                $total += $contribution;
            }

            $results[$order] = new SawResult($normalized, $weighted, round($total, self::DECIMALS), $missing[$order] ?? []);
        }

        return $results;
    }
}
