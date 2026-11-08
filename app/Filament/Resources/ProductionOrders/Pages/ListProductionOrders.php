<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Pages\ImportOrders;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\ProductionOrder;
use App\Services\Export\OrderSheetWriter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListProductionOrders extends ListRecords
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Impor Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(fn (): string => ImportOrders::getUrl())
                ->visible(fn (): bool => auth()->user()->can('import', ProductionOrder::class)),
            Action::make('export')
                ->label('Ekspor Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()->can('export', ProductionOrder::class))
                ->action(fn (): StreamedResponse => $this->exportToExcel()),
            CreateAction::make()->label('Tambah order'),
        ];
    }

    /**
     * Exports the list exactly as filtered, searched and sorted on screen.
     */
    protected function exportToExcel(): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'order');

        app(OrderSheetWriter::class)->writeOrders($path, $this->getFilteredSortedTableQuery());

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, 'order-produksi-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
