<?php

namespace Database\Seeders;

use App\Enums\CriterionDirection;
use App\Enums\ValueSource;
use App\Models\Criterion;
use App\Models\Setting;
use App\Services\CriterionWeights;
use Illuminate\Database\Seeder;

/**
 * Seven starting criteria with the proposed weights and the sample scales from the PRD (section 11.5).
 * Weights are set directly because the proposed values cannot be derived from whole importance levels.
 */
class CriteriaSeeder extends Seeder
{
    public function run(): void
    {
        $million = 1000000;

        $definitions = [
            [
                'C1', 'Sisa waktu menuju tenggat', 'hari', CriterionDirection::LowerFirst, ValueSource::Deadline, 5, 0.27,
                [[5, 'Kurang dari 2 hari', null, 2], [4, '2 sampai kurang dari 4 hari', 2, 4], [3, '4 sampai kurang dari 8 hari', 4, 8], [2, '8 sampai kurang dari 15 hari', 8, 15], [1, '15 hari ke atas', 15, null]],
            ],
            [
                'C2', 'Urgensi pelanggan', 'tingkat', CriterionDirection::HigherFirst, ValueSource::CustomerUrgency, 4, 0.18,
                [[5, 'Sangat mendesak', 5, null], [4, 'Mendesak', 4, 5], [3, 'Biasa', 3, 4], [2, 'Rendah', 2, 3], [1, 'Sangat rendah', null, 2]],
            ],
            [
                'C3', 'Ketersediaan bahan baku', 'persen', CriterionDirection::HigherFirst, ValueSource::Manual, 3, 0.135,
                [[5, '100 persen', 100, null], [4, '75 sampai kurang dari 100 persen', 75, 100], [3, '50 sampai kurang dari 75 persen', 50, 75], [2, '25 sampai kurang dari 50 persen', 25, 50], [1, 'Kurang dari 25 persen', null, 25]],
            ],
            [
                'C4', 'Sisa waktu pengerjaan', 'jam', CriterionDirection::LowerFirst, ValueSource::Manual, 3, 0.135,
                [[5, 'Kurang dari 8 jam', null, 8], [4, '8 sampai kurang dari 24 jam', 8, 24], [3, '24 sampai kurang dari 48 jam', 24, 48], [2, '48 sampai kurang dari 96 jam', 48, 96], [1, '96 jam ke atas', 96, null]],
            ],
            [
                'C5', 'Progres penyelesaian', 'persen', CriterionDirection::HigherFirst, ValueSource::Manual, 2, 0.09,
                [[5, '80 persen ke atas', 80, null], [4, '60 sampai kurang dari 80 persen', 60, 80], [3, '40 sampai kurang dari 60 persen', 40, 60], [2, '20 sampai kurang dari 40 persen', 20, 40], [1, 'Kurang dari 20 persen', null, 20]],
            ],
            [
                'C6', 'Nilai order', 'rupiah', CriterionDirection::HigherFirst, ValueSource::OrderValue, 2, 0.09,
                [[5, '100 juta ke atas', 100 * $million, null], [4, '50 sampai kurang dari 100 juta', 50 * $million, 100 * $million], [3, '25 sampai kurang dari 50 juta', 25 * $million, 50 * $million], [2, '10 sampai kurang dari 25 juta', 10 * $million, 25 * $million], [1, 'Kurang dari 10 juta', null, 10 * $million]],
            ],
            [
                'C7', 'Lama menunggu', 'hari', CriterionDirection::HigherFirst, ValueSource::Waiting, 3, 0.10,
                [[5, '7 hari ke atas', 7, null], [4, '5 sampai kurang dari 7 hari', 5, 7], [3, '3 sampai kurang dari 5 hari', 3, 5], [2, '1 sampai kurang dari 3 hari', 1, 3], [1, 'Kurang dari 1 hari', null, 1]],
            ],
        ];

        Setting::put(CriterionWeights::MODE_KEY, CriterionWeights::MODE_DIRECT);

        $percents = [];

        foreach ($definitions as $position => [$code, $name, $unit, $direction, $source, $importance, $weight, $scales]) {
            $criterion = Criterion::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'unit' => $unit,
                    'direction' => $direction,
                    'value_source' => $source,
                    'importance' => $importance,
                    'weight' => $weight,
                    'sort' => $position + 1,
                    'is_active' => true,
                ],
            );

            $percents[$criterion->id] = $weight * 100;

            foreach ($scales as [$score, $label, $lower, $upper]) {
                $criterion->scales()->updateOrCreate(
                    ['score' => $score],
                    ['label' => $label, 'lower_bound' => $lower, 'upper_bound' => $upper],
                );
            }
        }

        CriterionWeights::setDirect($percents);
    }
}
