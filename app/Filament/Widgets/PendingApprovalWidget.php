<?php

namespace App\Filament\Widgets;

use App\Enums\EvaluationStatus;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Evaluation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The evaluations that wait for the leader's decision (PRD F12 function 7). Only the leader sees it.
 */
class PendingApprovalWidget extends TableWidget
{
    /**
     * The widgets are cheap, so they come with the page instead of loading one by one afterwards.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isLeader() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Menunggu persetujuan Anda')
            ->description('Evaluasi yang sudah diajukan Manajer Produksi dan belum diputuskan.')
            ->query(fn () => Evaluation::query()
                ->where('status', EvaluationStatus::Submitted->value)
                ->orderBy('submitted_at')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('name')
                    ->label('Evaluasi')
                    ->url(fn (Evaluation $record): string => EvaluationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('submitted_at')
                    ->label('Diajukan')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('order_count')
                    ->label('Order dihitung')
                    ->alignEnd(),
                TextColumn::make('top_n')
                    ->label('Prioritas utama')
                    ->formatStateUsing(fn (int $state): string => $state.' order')
                    ->alignEnd(),
            ])
            ->recordUrl(fn (Evaluation $record): string => EvaluationResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada evaluasi yang menunggu')
            ->emptyStateDescription('Evaluasi yang diajukan Manajer Produksi akan muncul di sini.');
    }
}
