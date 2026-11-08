<?php

namespace App\Services;

use App\Models\Criterion;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Keeps the criterion weights consistent. Weights are handled in basis points
 * (1 weight = 10.000) so the total is exactly 1 and never drifts through rounding.
 */
class CriterionWeights
{
    public const MODE_KEY = 'weight_mode';

    public const MODE_IMPORTANCE = 'importance';

    public const MODE_DIRECT = 'direct';

    public const TOTAL = 10000;

    private static bool $refreshing = false;

    public static function isRefreshing(): bool
    {
        return self::$refreshing;
    }

    public static function mode(): string
    {
        return Setting::get(self::MODE_KEY, self::MODE_IMPORTANCE) === self::MODE_DIRECT
            ? self::MODE_DIRECT
            : self::MODE_IMPORTANCE;
    }

    /**
     * Weight of each criterion = its importance divided by the sum of importance.
     * Each result is rounded to 4 decimals and the rounding difference goes to the largest weight.
     *
     * @param  Collection<int, Criterion>  $criteria
     * @return array<int, int> basis points keyed by criterion id
     */
    public static function basisPointsFromImportance(Collection $criteria): array
    {
        $sum = (int) $criteria->sum('importance');
        $points = [];

        foreach ($criteria as $criterion) {
            $points[$criterion->id] = $sum > 0 ? (int) round($criterion->importance / $sum * self::TOTAL) : 0;
        }

        if ($points !== [] && $sum > 0) {
            $difference = self::TOTAL - array_sum($points);
            $largest = array_search(max($points), $points, true);
            $points[$largest] += $difference;
        }

        return $points;
    }

    /**
     * Recomputes every weight from the importance levels, only while the weights follow importance.
     */
    public static function refresh(): void
    {
        if (self::mode() !== self::MODE_IMPORTANCE) {
            return;
        }

        $points = self::basisPointsFromImportance(Criterion::query()->active()->get());

        foreach (Criterion::query()->where('is_active', false)->pluck('id') as $inactiveId) {
            $points[$inactiveId] = 0;
        }

        self::apply($points);
    }

    /**
     * @param  array<int, int>  $basisPoints
     */
    public static function apply(array $basisPoints): void
    {
        self::$refreshing = true;

        try {
            foreach (Criterion::query()->whereIn('id', array_keys($basisPoints))->get() as $criterion) {
                $weight = $basisPoints[$criterion->id] / self::TOTAL;

                if (round((float) $criterion->weight, 4) !== round($weight, 4)) {
                    $criterion->update(['weight' => $weight]);
                }
            }
        } finally {
            self::$refreshing = false;
        }
    }

    /**
     * @param  array<int, float|int|string>  $percentById  percentage per active criterion id, must total 100
     */
    public static function setDirect(array $percentById): void
    {
        $points = array_map(fn ($percent): int => (int) round((float) $percent * 100), $percentById);

        Setting::put(self::MODE_KEY, self::MODE_DIRECT);

        self::apply($points);
    }

    public static function useImportance(): void
    {
        Setting::put(self::MODE_KEY, self::MODE_IMPORTANCE);

        self::refresh();
    }

    /**
     * Ready means at least two active criteria, weights totalling exactly one,
     * and a complete, valid scale for every active criterion.
     *
     * @return array{total: int, active: int, ready: bool, message: string, missing_scales: array<int, string>}
     */
    public static function summary(): array
    {
        $active = Criterion::query()->active()->with('scales')->ordered()->get();
        $total = (int) round($active->sum(fn (Criterion $criterion): float => (float) $criterion->weight) * self::TOTAL);

        $missingScales = $active
            ->reject(fn (Criterion $criterion): bool => $criterion->hasValidScale())
            ->pluck('code')
            ->values()
            ->all();

        $message = match (true) {
            $active->count() < 2 => 'Belum siap dihitung: minimal dua kriteria harus aktif.',
            $total !== self::TOTAL => 'Belum siap dihitung: total bobot harus tepat 100%.',
            $missingScales !== [] => 'Belum siap dihitung: skala nilai '.implode(', ', $missingScales).' belum lengkap atau belum benar. Isi di menu Skala Nilai.',
            default => 'Siap dihitung.',
        };

        return [
            'total' => $total,
            'active' => $active->count(),
            'ready' => $active->count() >= 2 && $total === self::TOTAL && $missingScales === [],
            'message' => $message,
            'missing_scales' => $missingScales,
        ];
    }

    public static function formatPoints(int $basisPoints): string
    {
        return number_format($basisPoints / self::TOTAL, 4, ',', '.');
    }
}
