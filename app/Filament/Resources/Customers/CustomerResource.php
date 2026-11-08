<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
use BackedEnum;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'Pelanggan';

    protected static ?string $pluralModelLabel = 'Pelanggan';

    protected static ?string $navigationLabel = 'Pelanggan';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode pelanggan')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama pelanggan')
                    ->required()
                    ->maxLength(150),
                TextInput::make('contact')
                    ->label('Kontak')
                    ->helperText('Nomor telepon atau email. Boleh dikosongkan.')
                    ->maxLength(100),
                Select::make('urgency_level')
                    ->label('Tingkat urgensi')
                    ->options(Customer::URGENCY_LABELS)
                    ->default(3)
                    ->required()
                    ->native(false)
                    ->helperText('Seberapa mendesak pelanggan ini biasanya. Dipakai sebagai nilai awal penilaian order pelanggan ini.'),
                Toggle::make('is_active')
                    ->label('Pelanggan aktif')
                    ->default(true)
                    ->helperText('Pelanggan nonaktif tidak muncul di pilihan order baru, tetapi order lamanya tetap tampil.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'orders as active_orders_count' => fn (Builder $orders) => $orders->open(),
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact')
                    ->label('Kontak')
                    ->placeholder('-'),
                TextColumn::make('urgency_level')
                    ->label('Urgensi')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Customer::URGENCY_LABELS[$state] ?? (string) $state)
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'danger',
                        $state === 4 => 'warning',
                        $state === 3 => 'gray',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('active_orders_count')
                    ->label('Order aktif')
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
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
                        ->modalHeading('Nonaktifkan pelanggan')
                        ->modalDescription('Pelanggan tidak akan muncul di pilihan order baru. Order yang sudah ada tetap tampil.')
                        ->visible(fn (Customer $record): bool => $record->is_active && auth()->user()->can('update', $record))
                        ->action(function (Customer $record): void {
                            $record->update(['is_active' => false]);

                            Notification::make()->title('Pelanggan dinonaktifkan')->success()->send();
                        }),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (Customer $record): bool => ! $record->is_active && auth()->user()->can('update', $record))
                        ->action(function (Customer $record): void {
                            $record->update(['is_active' => true]);

                            Notification::make()->title('Pelanggan diaktifkan')->success()->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Belum ada pelanggan')
            ->emptyStateDescription('Tambahkan pelanggan lebih dulu supaya bisa dipilih saat membuat order.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomers::route('/'),
        ];
    }
}
