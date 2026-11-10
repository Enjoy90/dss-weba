<?php

namespace App\Filament\Widgets;

use App\Enums\EvaluationStatus;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * The final rank of one order in every evaluation, oldest to newest (PRD F18 function 5).
 * Rank 1 is drawn at the top, so a line that rises on the chart means the order is moving up the ranking.
 */
class OrderRankTrendChart extends ChartWidget
{
    /**
     * Only meant for the order page, never for the dashboard.
     */
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Tren peringkat';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?Model $record = null;

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): ?string
    {
        $points = $this->points();

        if ($points === []) {
            return 'Belum ada evaluasi yang memuat order ini. Grafik muncul setelah perhitungan dijalankan.';
        }

        $last = end($points);

        return 'Peringkat akhir pada setiap perhitungan. Perhitungan terakhir: peringkat '.$last['rank'].' dari '.$last['total'].'. Garis yang naik berarti order naik di daftar.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $points = $this->points();

        return [
            'datasets' => [[
                'label' => 'Peringkat akhir',
                'data' => array_column($points, 'rank'),
                'borderColor' => '#2563eb',
                'backgroundColor' => '#2563eb',
                'pointRadius' => 5,
                'tension' => 0.15,
            ]],
            'labels' => array_column($points, 'label'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => [
                    'reverse' => true,
                    'min' => 1,
                    'title' => ['display' => true, 'text' => 'Peringkat'],
                    'ticks' => ['precision' => 0, 'stepSize' => 1],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, rank: int, total: int}>
     */
    public function points(): array
    {
        if (! $this->record instanceof ProductionOrder) {
            return [];
        }

        return EvaluationItem::query()
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_items.evaluation_id')
            ->where('evaluation_items.production_order_id', $this->record->id)
            ->where('evaluations.status', '!=', EvaluationStatus::Canceled->value)
            ->orderBy('evaluations.evaluated_at')
            ->orderBy('evaluations.id')
            ->get(['evaluation_items.final_rank', 'evaluations.evaluated_at', 'evaluations.order_count'])
            ->map(fn ($row): array => [
                'label' => date('d/m H:i', strtotime((string) $row->evaluated_at)),
                'rank' => (int) $row->final_rank,
                'total' => (int) $row->order_count,
            ])
            ->all();
    }
}
