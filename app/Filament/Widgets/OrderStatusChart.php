<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\ProductionOrder;
use Filament\Widgets\ChartWidget;

/**
 * Number of orders in each status (PRD F12 function 5). The bar colors follow the status badges of the order list.
 */
class OrderStatusChart extends ChartWidget
{
    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Order per status';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '240px';

    private const COLORS = [
        'queued' => '#6b7280',
        'in_progress' => '#2563eb',
        'done' => '#16a34a',
        'canceled' => '#dc2626',
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): ?string
    {
        return 'Semua order yang tercatat, termasuk yang sudah selesai atau dibatalkan.';
    }

    /**
     * @return array<string, int> count by status value
     */
    public function counts(): array
    {
        $counts = ProductionOrder::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = $this->counts();

        return [
            'datasets' => [[
                'label' => 'Jumlah order',
                'data' => array_values($counts),
                'backgroundColor' => array_map(fn (string $status): string => self::COLORS[$status], array_keys($counts)),
                'borderRadius' => 4,
            ]],
            'labels' => array_map(fn (string $status): string => OrderStatus::from($status)->label(), array_keys($counts)),
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
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0, 'stepSize' => 1],
                    'title' => ['display' => true, 'text' => 'Jumlah order'],
                ],
            ],
        ];
    }
}
