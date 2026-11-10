<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AssessmentMatrix;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Services\Dashboard\DashboardFigures;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Six cards with the order counts (PRD F12 function 1). Every card opens the list it was counted from,
 * with the same filter switched on, so the number and the rows always agree.
 */
class OrderStatsOverview extends StatsOverviewWidget
{
    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|array|null $columns = 3;

    protected ?string $pollingInterval = null;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $counts = app(DashboardFigures::class)->counts();

        return [
            Stat::make('Order aktif', $counts['active'])
                ->description('Antri dan sedang dikerjakan')
                ->url(ProductionOrderResource::getUrl('index', ['filters' => ['status' => ['values' => ['queued', 'in_progress']]]])),
            Stat::make('Terlambat', $counts['late'])
                ->description('Lewat tenggat dan belum selesai')
                ->color($counts['late'] > 0 ? 'danger' : 'gray')
                ->url(ProductionOrderResource::getUrl('index', ['filters' => ['late' => ['isActive' => true]]])),
            Stat::make('Mendekati tenggat', $counts['near'])
                ->description('Tenggat dalam 3 hari')
                ->color($counts['near'] > 0 ? 'warning' : 'gray')
                ->url(ProductionOrderResource::getUrl('index', ['filters' => ['near_deadline' => ['isActive' => true]]])),
            Stat::make('Penilaian belum lengkap', $counts['incomplete'])
                ->description('Perlu dilengkapi sebelum dihitung')
                ->color($counts['incomplete'] > 0 ? 'warning' : 'gray')
                ->url(AssessmentMatrix::getUrl(['filters' => ['incomplete' => ['isActive' => true]]])),
            Stat::make('Terkunci', $counts['locked'])
                ->description('Kunci otomatis dan manual')
                ->url(ProductionOrderResource::getUrl('index', ['filters' => ['locked' => ['isActive' => true]]])),
            Stat::make('Tertahan', $counts['held'])
                ->description('Di luar prioritas berkali-kali')
                ->color($counts['held'] > 0 ? 'warning' : 'gray')
                ->url(ProductionOrderResource::getUrl('index', ['filters' => ['held' => ['isActive' => true]]])),
        ];
    }
}
