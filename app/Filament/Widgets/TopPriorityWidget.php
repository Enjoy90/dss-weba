<?php

namespace App\Filament\Widgets;

use App\Enums\EvaluationStatus;
use App\Enums\OrderPriority;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The orders that carry the Prioritas Utama label, in the rank the latest applied evaluation gave them
 * (PRD F12 function 2). An order that has since been finished or canceled lost its label and leaves the list.
 */
class TopPriorityWidget extends TableWidget
{
    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static function appliedEvaluation(): ?Evaluation
    {
        return Evaluation::query()
            ->where('status', EvaluationStatus::Applied->value)
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->first();
    }

    public function table(Table $table): Table
    {
        $evaluation = static::appliedEvaluation();

        return $table
            ->heading('Prioritas utama')
            ->description($evaluation === null
                ? null
                : 'Dari evaluasi "'.$evaluation->name.'", diterapkan '.$evaluation->applied_at->format('d/m/Y H:i').'.')
            ->query(fn () => EvaluationItem::query()
                ->with('order.customer')
                ->where('evaluation_id', $evaluation?->id ?? 0)
                ->whereHas('order', fn ($query) => $query->where('priority', OrderPriority::Top->value))
                ->orderBy('final_rank'))
            ->columns([
                TextColumn::make('final_rank')
                    ->label('Peringkat')
                    ->alignCenter(),
                TextColumn::make('order.number')
                    ->label('Order')
                    ->description(fn (EvaluationItem $record): string => $record->order->customer->name)
                    ->url(fn (EvaluationItem $record): string => ProductionOrderResource::getUrl('view', ['record' => $record->order])),
                TextColumn::make('order.deadline_date')
                    ->label('Tenggat')
                    ->date('d/m/Y'),
                TextColumn::make('score')
                    ->label('Skor')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 4, ',', '.'))
                    ->alignEnd(),
                TextColumn::make('order.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color()),
            ])
            ->headerActions([])
            ->paginated(false)
            ->emptyStateHeading($evaluation === null ? 'Belum ada evaluasi yang diterapkan' : 'Tidak ada order berlabel Prioritas Utama')
            ->emptyStateDescription($evaluation === null
                ? 'Daftar ini terisi setelah Pimpinan menyetujui evaluasi dan Manajer menerapkannya.'
                : 'Order yang berlabel sudah selesai atau dibatalkan.');
    }
}
