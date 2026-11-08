<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Models\ActivityLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Riwayat perubahan';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i'),
                TextColumn::make('user.name')
                    ->label('Oleh')
                    ->placeholder('Sistem'),
                TextColumn::make('action')
                    ->label('Kegiatan')
                    ->formatStateUsing(fn (string $state, ActivityLog $record): string => $record->actionLabel()),
                TextColumn::make('changes')
                    ->label('Perubahan')
                    ->state(fn (ActivityLog $record): array => $record->changeLines(! auth()->user()->canSeeOrderValue()))
                    ->listWithLineBreaks()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading('Belum ada riwayat')
            ->emptyStateDescription('Perubahan pada order ini akan tercatat di sini.');
    }
}
