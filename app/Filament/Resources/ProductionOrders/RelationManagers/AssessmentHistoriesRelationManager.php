<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Enums\ValueSource;
use App\Models\AssessmentHistory;
use App\Services\AssessmentFormatter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssessmentHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'assessmentHistories';

    protected static ?string $title = 'Riwayat nilai';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with(['assessment.criterion', 'changer']);

                if (! auth()->user()->canSeeOrderValue()) {
                    $query->whereHas('assessment.criterion', fn (Builder $criteria) => $criteria->where('value_source', '!=', ValueSource::OrderValue->value));
                }

                return $query;
            })
            ->columns([
                TextColumn::make('changed_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i'),
                TextColumn::make('criterion')
                    ->label('Kriteria')
                    ->state(fn (AssessmentHistory $record): string => $record->assessment->criterion->code.' '.$record->assessment->criterion->name),
                TextColumn::make('old_value')
                    ->label('Nilai lama')
                    ->state(fn (AssessmentHistory $record): string => $record->old_value === null ? 'kosong' : AssessmentFormatter::raw($record->assessment->criterion, (float) $record->old_value)),
                TextColumn::make('new_value')
                    ->label('Nilai baru')
                    ->state(fn (AssessmentHistory $record): string => $record->new_value === null ? 'kosong' : AssessmentFormatter::raw($record->assessment->criterion, (float) $record->new_value)),
                TextColumn::make('changer.name')
                    ->label('Oleh')
                    ->placeholder('Sistem'),
            ])
            ->defaultSort('changed_at', 'desc')
            ->defaultKeySort(false)
            ->paginated([10, 25])
            ->emptyStateHeading('Belum ada riwayat nilai')
            ->emptyStateDescription('Perubahan nilai manual dan penimpaan urgensi untuk order ini akan tercatat di sini.');
    }
}
