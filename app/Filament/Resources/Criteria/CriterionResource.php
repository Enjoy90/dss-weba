<?php

namespace App\Filament\Resources\Criteria;

use App\Enums\CriterionDirection;
use App\Enums\ValueSource;
use App\Filament\Resources\Criteria\Pages\ManageCriteria;
use App\Models\Criterion;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use UnitEnum;

class CriterionResource extends Resource
{
    protected static ?string $model = Criterion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $modelLabel = 'Kriteria';

    protected static ?string $pluralModelLabel = 'Kriteria dan Bobot';

    protected static ?string $navigationLabel = 'Kriteria dan Bobot';

    protected static string|UnitEnum|null $navigationGroup = 'Prioritas';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->helperText('Singkat dan unik, misalnya C8.'),
                TextInput::make('name')
                    ->label('Nama kriteria')
                    ->required()
                    ->maxLength(120),
                TextInput::make('unit')
                    ->label('Satuan')
                    ->maxLength(30)
                    ->datalist(['hari', 'persen', 'jam', 'rupiah', 'tingkat'])
                    ->helperText('Satuan nilai asli, misalnya hari, persen, jam, rupiah, atau tingkat.'),
                Select::make('direction')
                    ->label('Arah nilai asli')
                    ->options(CriterionDirection::options())
                    ->required()
                    ->native(false)
                    ->helperText('Makin besar didahulukan, atau makin kecil didahulukan. Contoh: sisa hari tenggat makin kecil makin didahulukan.'),
                Select::make('value_source')
                    ->label('Sumber nilai')
                    ->options(ValueSource::options())
                    ->default(ValueSource::Manual->value)
                    ->required()
                    ->native(false)
                    ->helperText('Otomatis diambil dari data order, atau manual diisi pengguna.'),
                Select::make('importance')
                    ->label('Tingkat kepentingan')
                    ->options(Criterion::IMPORTANCE_LABELS)
                    ->default(3)
                    ->required()
                    ->native(false)
                    ->helperText('Bobot dihitung dari tingkat kepentingan. Mengubahnya menghitung ulang semua bobot, kecuali bobot sedang diatur langsung.'),
                Toggle::make('is_active')
                    ->label('Kriteria aktif')
                    ->default(true)
                    ->rules([
                        fn (?Criterion $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($value || $record === null || ! $record->is_active) {
                                return;
                            }

                            $reason = static::deactivationBlockedReason($record);

                            if ($reason !== null) {
                                $fail($reason);
                            }
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->ordered())
            ->paginated(false)
            ->columns([
                TextColumn::make('code')
                    ->label('Kode'),
                TextColumn::make('name')
                    ->label('Nama'),
                TextColumn::make('unit')
                    ->label('Satuan')
                    ->placeholder('-'),
                TextColumn::make('direction')
                    ->label('Arah nilai')
                    ->formatStateUsing(fn (CriterionDirection $state): string => $state->label()),
                TextColumn::make('value_source')
                    ->label('Sumber nilai')
                    ->formatStateUsing(fn (ValueSource $state): string => $state->label()),
                TextColumn::make('importance')
                    ->label('Kepentingan')
                    ->formatStateUsing(fn (int $state): string => Criterion::IMPORTANCE_LABELS[$state] ?? (string) $state)
                    ->alignEnd(),
                TextColumn::make('weight')
                    ->label('Bobot')
                    ->state(fn (Criterion $record): string => $record->is_active
                        ? number_format((float) $record->weight, 4, ',', '.').' ('.rtrim(rtrim(number_format($record->weightPercent(), 2, ',', '.'), '0'), ',').'%)'
                        : '-')
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Ubah'),
                    Action::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan kriteria')
                        ->modalDescription('Kriteria tidak ikut perhitungan berikutnya. Evaluasi lama tidak berubah.')
                        ->visible(fn (Criterion $record): bool => $record->is_active && auth()->user()->can('update', $record))
                        ->action(function (Criterion $record): void {
                            $reason = static::deactivationBlockedReason($record);

                            if ($reason !== null) {
                                Notification::make()->title($reason)->danger()->send();

                                return;
                            }

                            $record->update(['is_active' => false]);

                            Notification::make()->title('Kriteria dinonaktifkan')->success()->send();
                        }),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (Criterion $record): bool => ! $record->is_active && auth()->user()->can('update', $record))
                        ->action(function (Criterion $record): void {
                            $record->update(['is_active' => true]);

                            Notification::make()->title('Kriteria diaktifkan')->success()->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Belum ada kriteria')
            ->emptyStateDescription('Tambahkan kriteria penilaian. Perhitungan butuh minimal dua kriteria aktif.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCriteria::route('/'),
        ];
    }

    public static function deactivationBlockedReason(Criterion $criterion): ?string
    {
        if (Criterion::query()->active()->whereKeyNot($criterion->getKey())->count() < 2) {
            return 'Minimal dua kriteria harus tetap aktif.';
        }

        return null;
    }
}
