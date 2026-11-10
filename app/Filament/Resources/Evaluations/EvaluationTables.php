<?php

namespace App\Filament\Resources\Evaluations;

use App\Enums\CriterionDirection;
use App\Enums\ValueSource;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\User;
use App\Services\AssessmentFormatter;
use Illuminate\Support\Collection;

/**
 * Plain tables for the read-only tabs of an evaluation, built from the copy saved with it.
 * The criterion for the order value is left out for a user who may not see rupiah (the supervisor).
 */
class EvaluationTables
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $criteria;

    /**
     * @var Collection<int, EvaluationItem>|null
     */
    private ?Collection $items = null;

    public function __construct(private readonly Evaluation $evaluation, private readonly User $viewer)
    {
        $this->criteria = collect($evaluation->criteria_snapshot)
            ->reject(fn (array $criterion): bool => $criterion['value_source'] === ValueSource::OrderValue->value && ! $viewer->canSeeOrderValue())
            ->values()
            ->all();
    }

    /**
     * True when a criterion is hidden from this viewer, so a table can say that its columns are not complete.
     */
    public function hidesCriteria(): bool
    {
        return count($this->criteria) < count($this->evaluation->criteria_snapshot);
    }

    /**
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function rawValues(): array
    {
        return $this->matrix(fn (EvaluationItem $item, array $criterion): string => AssessmentFormatter::withUnit(
            $criterion['unit'],
            ($item->raw_values[$criterion['code']] ?? null) === null ? null : (float) $item->raw_values[$criterion['code']],
        ));
    }

    /**
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function scaleValues(): array
    {
        return $this->matrix(fn (EvaluationItem $item, array $criterion): string => (string) ($item->scale_values[$criterion['code']] ?? '-'));
    }

    /**
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function normalizedValues(): array
    {
        return $this->matrix(fn (EvaluationItem $item, array $criterion): string => $this->decimal($item->normalized_values[$criterion['code']] ?? 0));
    }

    /**
     * Weight times normalized value for each criterion, and the score they add up to.
     *
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function weightedValues(): array
    {
        $table = $this->matrix(
            fn (EvaluationItem $item, array $criterion): string => $this->decimal($item->weighted_values[$criterion['code']] ?? 0),
            fn (array $criterion): string => $criterion['code'].' (bobot '.$this->decimal($criterion['weight']).')',
        );

        $table['headings'][] = 'Skor V';

        foreach ($this->items() as $index => $item) {
            $table['rows'][$index][] = $this->decimal((float) $item->score);
        }

        return $table;
    }

    /**
     * One row per scale of each criterion, with the criterion shown on its first row only.
     *
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function criteriaAndScales(): array
    {
        $rows = [];

        foreach ($this->criteria as $criterion) {
            foreach ($criterion['scales'] as $index => $scale) {
                $rows[] = [
                    $index === 0 ? $criterion['code'].' '.$criterion['name'] : '',
                    $index === 0 ? number_format($criterion['weight'] * 100, 2, ',', '.').'%' : '',
                    $index === 0 ? CriterionDirection::from($criterion['direction'])->label() : '',
                    (string) $scale['score'],
                    $scale['label'] ?? '-',
                ];
            }
        }

        return ['headings' => ['Kriteria', 'Bobot', 'Arah nilai asli', 'Skala', 'Rentang'], 'rows' => $rows];
    }

    /**
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function warnings(): array
    {
        $hidden = collect($this->evaluation->criteria_snapshot)->pluck('code')->diff(collect($this->criteria)->pluck('code'))->all();
        $rows = [];

        foreach ($this->items() as $item) {
            foreach ($item->warnings ?? [] as $warning) {
                if (in_array($warning['criterion'], $hidden, true)) {
                    continue;
                }

                $rows[] = [
                    (string) $item->final_rank,
                    $item->order_snapshot['number'].' ('.$item->order_snapshot['customer'].')',
                    $warning['criterion'],
                    $warning['type'] === 'empty' ? 'Nilai kosong, dihitung sebagai skala 1' : 'Nilai manual sudah lama dan perlu diperbarui',
                ];
            }
        }

        return ['headings' => ['Peringkat', 'Order', 'Kriteria', 'Peringatan'], 'rows' => $rows];
    }

    /**
     * @param  callable(EvaluationItem, array<string, mixed>): string  $cell
     * @param  (callable(array<string, mixed>): string)|null  $heading
     * @return array{headings: array<int, string>, rows: array<int, array<int, string>>}
     */
    private function matrix(callable $cell, ?callable $heading = null): array
    {
        $headings = ['Peringkat', 'Order'];

        foreach ($this->criteria as $criterion) {
            $headings[] = $heading === null ? $criterion['code'] : $heading($criterion);
        }

        $rows = [];

        foreach ($this->items() as $item) {
            $row = [(string) $item->final_rank, $item->order_snapshot['number'].' ('.$item->order_snapshot['customer'].')'];

            foreach ($this->criteria as $criterion) {
                $row[] = $cell($item, $criterion);
            }

            $rows[] = $row;
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * @return Collection<int, EvaluationItem>
     */
    private function items(): Collection
    {
        return $this->items ??= $this->evaluation->items()->get()->values();
    }

    private function decimal(float|int $value): string
    {
        return number_format((float) $value, 4, ',', '.');
    }
}
