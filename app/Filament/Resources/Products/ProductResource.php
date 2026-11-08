<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?string $navigationLabel = 'Produk';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode produk')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama produk')
                    ->required()
                    ->maxLength(150),
                TextInput::make('category')
                    ->label('Kategori')
                    ->maxLength(80),
                TextInput::make('unit_price')
                    ->label('Harga satuan')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp')
                    ->helperText('Per pasang. Dipakai untuk menghitung nilai order secara otomatis. Boleh dikosongkan.'),
                TextInput::make('minutes_per_unit')
                    ->label('Waktu pengerjaan per pasang')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('menit')
                    ->helperText('Perkiraan saja. Boleh dikosongkan.'),
                Toggle::make('is_active')
                    ->label('Produk aktif')
                    ->default(true)
                    ->helperText('Produk nonaktif tidak muncul di pilihan order baru, tetapi order lamanya tetap tampil.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Harga satuan')
                    ->money('IDR', locale: 'id')
                    ->placeholder('-')
                    ->alignEnd()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()->canSeeOrderValue()),
                TextColumn::make('minutes_per_unit')
                    ->label('Menit per pasang')
                    ->placeholder('-')
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(fn (): array => Product::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
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
                        ->modalHeading('Nonaktifkan produk')
                        ->modalDescription('Produk tidak akan muncul di pilihan order baru. Order yang sudah ada tetap tampil.')
                        ->visible(fn (Product $record): bool => $record->is_active && auth()->user()->can('update', $record))
                        ->action(function (Product $record): void {
                            $record->update(['is_active' => false]);

                            Notification::make()->title('Produk dinonaktifkan')->success()->send();
                        }),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (Product $record): bool => ! $record->is_active && auth()->user()->can('update', $record))
                        ->action(function (Product $record): void {
                            $record->update(['is_active' => true]);

                            Notification::make()->title('Produk diaktifkan')->success()->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Belum ada produk')
            ->emptyStateDescription('Tambahkan produk lebih dulu supaya bisa dipilih saat membuat order.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }
}
