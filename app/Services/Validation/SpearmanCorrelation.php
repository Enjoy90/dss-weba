<?php

namespace App\Services\Validation;

use InvalidArgumentException;

/**
 * Spearman rank correlation for task P10-04 (PRD 13 point 5): how close the ranking of the system is to the order the
 * company would choose. Tied places get the average of the ranks they share, and rho is the Pearson correlation of the
 * two rank lists, which equals 1 - 6 sum(d^2) / (n (n^2 - 1)) when there are no ties.
 */
class SpearmanCorrelation
{
    /**
     * @param  array<string, float|int>  $system  place per order in the system ranking
     * @param  array<string, float|int>  $company  place per order in the company order, same keys
     * @return array{rho: float, n: int, rows: array<string, array{system: float, company: float, d: float, d2: float}>, sum_d2: float}
     */
    public function compare(array $system, array $company): array
    {
        $keys = array_keys($system);
        sort($keys);
        $other = array_keys($company);
        sort($other);

        if ($keys !== $other) {
            throw new InvalidArgumentException('Kedua urutan harus memuat order yang sama.');
        }

        if (count($keys) < 3) {
            throw new InvalidArgumentException('Korelasi butuh sedikitnya tiga order.');
        }

        $a = $this->ranks($system);
        $b = $this->ranks($company);
        $rows = [];
        $sum = 0.0;

        foreach ($keys as $key) {
            $d = $a[$key] - $b[$key];
            $rows[$key] = ['system' => $a[$key], 'company' => $b[$key], 'd' => $d, 'd2' => $d * $d];
            $sum += $d * $d;
        }

        return ['rho' => round($this->pearson($a, $b), 4), 'n' => count($keys), 'rows' => $rows, 'sum_d2' => $sum];
    }

    /**
     * How strong a correlation is, in the usual words.
     */
    public static function strength(float $rho): string
    {
        $abs = abs($rho);

        return match (true) {
            $abs >= 0.9 => 'sangat kuat',
            $abs >= 0.7 => 'kuat',
            $abs >= 0.5 => 'sedang',
            $abs >= 0.3 => 'lemah',
            default => 'sangat lemah',
        };
    }

    /**
     * Positions 1..n with ties given their average position.
     *
     * @param  array<string, float|int>  $values
     * @return array<string, float>
     */
    private function ranks(array $values): array
    {
        asort($values);
        $keys = array_keys($values);
        $ranks = [];
        $i = 0;

        while ($i < count($keys)) {
            $j = $i;

            while ($j + 1 < count($keys) && $values[$keys[$j + 1]] == $values[$keys[$i]]) {
                $j++;
            }

            $average = ($i + $j) / 2 + 1;

            for ($k = $i; $k <= $j; $k++) {
                $ranks[$keys[$k]] = $average;
            }

            $i = $j + 1;
        }

        return $ranks;
    }

    /**
     * @param  array<string, float>  $a
     * @param  array<string, float>  $b
     */
    private function pearson(array $a, array $b): float
    {
        $n = count($a);
        $meanA = array_sum($a) / $n;
        $meanB = array_sum($b) / $n;
        $cov = 0.0;
        $varA = 0.0;
        $varB = 0.0;

        foreach ($a as $key => $value) {
            $cov += ($value - $meanA) * ($b[$key] - $meanB);
            $varA += ($value - $meanA) ** 2;
            $varB += ($b[$key] - $meanB) ** 2;
        }

        return $varA == 0.0 || $varB == 0.0 ? 0.0 : $cov / sqrt($varA * $varB);
    }
}
