<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\ProductionOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The open orders that are furthest past their deadline (PRD F12 function 3). The same orders the
 * "Terlambat" card counts, the most overdue first.
 */
class LateOrdersWidget extends TableWidget
{
    public const LIMIT = 5;

    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Order terlambat terlama')
            ->description('Lima order yang paling lama lewat tenggat.')
            ->query(fn () => ProductionOrder::query()
                ->with('customer')
                ->late()
                ->orderBy('deadline_date')
                ->orderBy('id')
                ->limit(self::LIMIT))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->description(fn (ProductionOrder $record): string => $record->customer->name)
                    ->url(fn (ProductionOrder $record): string => ProductionOrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('deadline_date')
                    ->label('Tenggat')
                    ->date('d/m/Y'),
                TextColumn::make('days_late')
                    ->label('Terlambat')
                    ->state(fn (ProductionOrder $record): string => abs($record->daysLeft()).' hari')
                    ->badge()
                    ->color('danger'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak ada order terlambat')
            ->emptyStateDescription('Semua order yang masih berjalan belum melewati tenggat.');
    }
}
