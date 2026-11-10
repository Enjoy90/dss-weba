<?php

namespace App\Filament\Resources\Evaluations;

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
use App\Filament\Pages\RunEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Pages\ViewEvaluation;
use App\Models\Evaluation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EvaluationResource extends Resource
{
    protected static ?string $model = Evaluation::class;

    protected static ?string $modelLabel = 'Evaluasi';

    protected static ?string $pluralModelLabel = 'Hasil Evaluasi';

    protected static ?string $navigationLabel = 'Hasil Evaluasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Prioritas';

    protected static ?int $navigationSort = 5;

    /**
     * Evaluations are created by running a calculation, never by a form.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['evaluator', 'approver']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('evaluated_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('trigger')
                    ->label('Pemicu')
                    ->badge()
                    ->formatStateUsing(fn (EvaluationTrigger $state): string => $state->label())
                    ->color('gray'),
                TextColumn::make('order_count')
                    ->label('Order')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (EvaluationStatus $state): string => $state->label())
                    ->color(fn (EvaluationStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('evaluator.name')
                    ->label('Dijalankan oleh')
                    ->placeholder('Sistem (terjadwal)'),
                TextColumn::make('approver.name')
                    ->label('Disetujui oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('evaluated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(EvaluationStatus::cases())->mapWithKeys(fn (EvaluationStatus $status): array => [$status->value => $status->label()])->all())
                    ->multiple(),
                SelectFilter::make('trigger')
                    ->label('Pemicu')
                    ->options(collect(EvaluationTrigger::cases())->mapWithKeys(fn (EvaluationTrigger $trigger): array => [$trigger->value => $trigger->label()])->all()),
                Filter::make('period')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false)->displayFormat('d M Y'),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false)->displayFormat('d M Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('evaluated_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('evaluated_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari '.date('d/m/Y', strtotime($data['from']));
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai '.date('d/m/Y', strtotime($data['until']));
                        }

                        return $indicators;
                    }),
            ])
            ->recordUrl(fn (Evaluation $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada evaluasi')
            ->emptyStateDescription('Evaluasi tersimpan setiap kali perhitungan dijalankan. Manajer menjalankannya di menu Jalankan Perhitungan.')
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->emptyStateActions([
                Action::make('run')
                    ->label('Buka Jalankan Perhitungan')
                    ->url(fn (): string => RunEvaluation::getUrl())
                    ->visible(fn (): bool => RunEvaluation::canAccess()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluations::route('/'),
            'view' => ViewEvaluation::route('/{record}'),
        ];
    }
}
