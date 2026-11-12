<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UnitEnum;

/**
 * Who did what and when (PRD F14). A read-only list for the admin and the leader, with filters for the user,
 * the kind of activity and the date.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $modelLabel = 'Log';

    protected static ?string $pluralModelLabel = 'Log Aktivitas';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $slug = 'log-aktivitas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 20;

    /**
     * The log page is for the admin and the leader only, whatever the policy says about reading one order's history.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isLeader());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user',
            'subject' => fn (MorphTo $morph): MorphTo => $morph->morphWith([Assessment::class => ['order', 'criterion']]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->placeholder('Sistem'),
                TextColumn::make('action')
                    ->label('Kegiatan')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state, ActivityLog $record): string => $record->actionLabel())
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Objek')
                    ->state(fn (ActivityLog $record): string => $record->subjectLabel())
                    ->placeholder('-'),
                TextColumn::make('changes')
                    ->label('Perubahan')
                    ->state(fn (ActivityLog $record): array => $record->changeLines())
                    ->listWithLineBreaks()
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('ip_address')
                    ->label('Alamat IP')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('action')
                    ->label('Jenis kegiatan')
                    ->options(fn (): array => ActivityLog::actionOptions())
                    ->multiple(),
                Filter::make('period')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false)->displayFormat('d M Y'),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false)->displayFormat('d M Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada catatan')
            ->emptyStateDescription('Kegiatan penting seperti masuk, perubahan order, perhitungan, dan persetujuan akan tercatat di sini.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
