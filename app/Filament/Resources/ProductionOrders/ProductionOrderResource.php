<?php

namespace App\Filament\Resources\ProductionOrders;

use App\Enums\Completeness;
use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\CreateProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\EditProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\Pages\ViewProductionOrder;
use App\Filament\Resources\ProductionOrders\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\ProductionOrders\RelationManagers\AssessmentHistoriesRelationManager;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\AssessmentFormatter;
use App\Services\AssessmentResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Order Produksi';

    protected static ?string $navigationLabel = 'Order Produksi';

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data order')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Nomor order')
                            ->placeholder('Dibuat otomatis')
                            ->helperText('Kosongkan untuk nomor otomatis. Boleh diubah bila perlu.')
                            ->maxLength(40)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->label('Status')
                            ->options(OrderStatus::options())
                            ->default(OrderStatus::Queued->value)
                            ->required()
                            ->native(false),
                        Select::make('customer_id')
                            ->label('Pelanggan')
                            ->options(fn (?ProductionOrder $record): array => static::customerOptions($record))
                            ->required()
                            ->searchable()
                            ->native(false),
                        Select::make('product_id')
                            ->label('Produk')
                            ->options(fn (?ProductionOrder $record): array => static::productOptions($record))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $price = $state ? Product::query()->whereKey($state)->value('unit_price') : null;

                                if ($price !== null) {
                                    $set('unit_price', $price);
                                }
                            }),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('pasang')
                            ->live(onBlur: true),
                        TextInput::make('unit_price')
                            ->label('Harga satuan')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->helperText('Terisi otomatis dari produk. Boleh diubah untuk order ini.')
                            ->live(onBlur: true),
                        DatePicker::make('order_date')
                            ->label('Tanggal masuk')
                            ->default(today())
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->live(),
                        DatePicker::make('deadline_date')
                            ->label('Tenggat')
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->afterOrEqual('order_date'),
                        TextEntry::make('order_total')
                            ->label('Nilai order')
                            ->state(function (Get $get): string {
                                $quantity = (float) $get('quantity');
                                $price = (float) $get('unit_price');

                                if ($quantity <= 0 || $price <= 0) {
                                    return '-';
                                }

                                return static::rupiah($quantity * $price);
                            })
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Nomor order'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                            ->color(fn (OrderStatus $state): string => $state->color()),
                        TextEntry::make('priority')
                            ->label('Prioritas')
                            ->badge()
                            ->formatStateUsing(fn (OrderPriority $state): string => $state->label())
                            ->color(fn (OrderPriority $state): string => $state->color()),
                        TextEntry::make('customer.name')->label('Pelanggan'),
                        TextEntry::make('product.name')->label('Produk'),
                        TextEntry::make('quantity')
                            ->label('Jumlah')
                            ->suffix(' pasang'),
                        TextEntry::make('order_date')
                            ->label('Tanggal masuk')
                            ->date('d M Y'),
                        TextEntry::make('deadline_date')
                            ->label('Tenggat')
                            ->date('d M Y'),
                        TextEntry::make('days_left')
                            ->label('Sisa hari')
                            ->state(fn (ProductionOrder $record): string => static::daysLeftText($record))
                            ->badge()
                            ->color(fn (ProductionOrder $record): string => static::daysLeftColor($record)),
                        TextEntry::make('unit_price')
                            ->label('Harga satuan')
                            ->visible(fn (): bool => auth()->user()->canSeeOrderValue())
                            ->state(fn (ProductionOrder $record): string => $record->unit_price === null ? '-' : static::rupiah((float) $record->unit_price)),
                        TextEntry::make('total_value')
                            ->label('Nilai order')
                            ->visible(fn (): bool => auth()->user()->canSeeOrderValue())
                            ->state(fn (ProductionOrder $record): string => $record->totalValue() === null ? '-' : static::rupiah($record->totalValue())),
                        TextEntry::make('waiting')
                            ->label('Lama menunggu')
                            ->state(fn (ProductionOrder $record): string => $record->status === OrderStatus::Queued ? $record->waitingDays().' hari' : '-'),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Penguncian')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('lock_type')
                            ->label('Status kunci')
                            ->formatStateUsing(fn (LockType $state): string => $state->label()),
                        TextEntry::make('lock_reason')
                            ->label('Alasan')
                            ->placeholder('-'),
                        TextEntry::make('locker.name')
                            ->label('Dikunci oleh')
                            ->placeholder('-'),
                        TextEntry::make('locked_at')
                            ->label('Waktu kunci')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('-'),
                        TextEntry::make('lock_until')
                            ->label('Kunci berakhir')
                            ->date('d M Y')
                            ->placeholder('Tanpa batas'),
                    ]),
                Section::make('Penilaian')
                    ->columnSpanFull()
                    ->description('Nilai tiap kriteria untuk order ini. Nilai otomatis selalu terbaru. Ubah nilai manual di menu Penilaian Order.')
                    ->schema([
                        TextEntry::make('completeness')
                            ->label('Kelengkapan')
                            ->badge()
                            ->state(fn (ProductionOrder $record): string => static::completenessOf($record)->label())
                            ->color(fn (ProductionOrder $record): string => static::completenessOf($record)->color()),
                        RepeatableEntry::make('assessment_rows')
                            ->label('Nilai per kriteria')
                            ->contained(false)
                            ->columns(4)
                            ->state(fn (ProductionOrder $record): array => static::assessmentRows($record))
                            ->schema([
                                TextEntry::make('criterion')->label('Kriteria'),
                                TextEntry::make('value')->label('Nilai asli'),
                                TextEntry::make('score')->label('Skala'),
                                TextEntry::make('source')->label('Sumber'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'product']))
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Jml')
                    ->suffix(' psg')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Masuk')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deadline_date')
                    ->label('Tenggat')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('days_left')
                    ->label('Sisa hari')
                    ->state(fn (ProductionOrder $record): string => static::daysLeftText($record))
                    ->badge()
                    ->color(fn (ProductionOrder $record): string => static::daysLeftColor($record)),
                TextColumn::make('waiting')
                    ->label('Menunggu')
                    ->state(fn (ProductionOrder $record): string => $record->status === OrderStatus::Queued ? $record->waitingDays().' hari' : '-')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),
                IconColumn::make('lock_type')
                    ->label('Kunci')
                    ->icon(fn (LockType $state): ?Heroicon => $state->isLocked() ? Heroicon::LockClosed : null)
                    ->color(fn (LockType $state): string => $state === LockType::Manual ? 'warning' : 'gray')
                    ->tooltip(fn (ProductionOrder $record): ?string => $record->isLocked()
                        ? $record->lock_type->label().($record->lock_reason ? ': '.$record->lock_reason : '')
                        : null),
                TextColumn::make('priority')
                    ->label('Prioritas')
                    ->badge()
                    ->formatStateUsing(fn (OrderPriority $state): string => $state->label())
                    ->color(fn (OrderPriority $state): string => $state->color())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('deadline_date')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderStatus::options())
                    ->multiple(),
                Filter::make('late')
                    ->label('Terlambat')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->late()),
                Filter::make('near_deadline')
                    ->label('Mendekati tenggat (3 hari)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->nearDeadline()),
                Filter::make('locked')
                    ->label('Terkunci')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->locked()),
            ])
            ->recordUrl(fn (ProductionOrder $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Ubah'),
                    Action::make('changeStatus')
                        ->label('Ubah status')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (ProductionOrder $record): bool => auth()->user()->can('changeStatus', $record))
                        ->modalHeading(fn (ProductionOrder $record): string => 'Ubah status '.$record->number)
                        ->modalSubmitActionLabel('Simpan status')
                        ->fillForm(fn (ProductionOrder $record): array => ['status' => $record->status->value])
                        ->schema([
                            Select::make('status')
                                ->label('Status baru')
                                ->options(OrderStatus::options())
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (ProductionOrder $record, array $data): void {
                            $record->update(['status' => $data['status']]);

                            Notification::make()
                                ->title('Status order diubah menjadi '.OrderStatus::from($data['status'])->label())
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Belum ada order')
            ->emptyStateDescription('Order yang dicatat akan tampil di sini. Pastikan pelanggan dan produk sudah ada.');
    }

    public static function getRelations(): array
    {
        return [
            AssessmentHistoriesRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    /**
     * One row per criterion the viewer may see, for the assessment section of the detail page.
     *
     * @return array<int, array{criterion: string, value: string, score: string, source: string}>
     */
    public static function assessmentRows(ProductionOrder $order): array
    {
        $values = AssessmentResolver::make()->resolveAll($order->loadMissing(['customer', 'assessments']), static::allActiveCriteria());

        return static::visibleCriteria()->map(function (Criterion $criterion) use ($values): array {
            $value = $values->get($criterion->id);

            return [
                'criterion' => $criterion->code.' '.$criterion->name,
                'value' => AssessmentFormatter::raw($criterion, $value->raw),
                'score' => $value->score === null ? '-' : (string) $value->score,
                'source' => $value->source->label().($value->stale ? ', perlu diperbarui' : ''),
            ];
        })->values()->all();
    }

    public static function completenessOf(ProductionOrder $order): Completeness
    {
        $resolver = AssessmentResolver::make();

        return $resolver->completeness($resolver->resolveAll($order->loadMissing(['customer', 'assessments']), static::allActiveCriteria()));
    }

    /**
     * @return Collection<int, Criterion>
     */
    protected static function allActiveCriteria(): Collection
    {
        return Criterion::query()->active()->ordered()->with('scales')->get();
    }

    /**
     * @return Collection<int, Criterion>
     */
    protected static function visibleCriteria(): Collection
    {
        return static::allActiveCriteria()
            ->filter(fn (Criterion $criterion): bool => Gate::allows('viewCriterion', [Assessment::class, $criterion]))
            ->values();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionOrders::route('/'),
            'create' => CreateProductionOrder::route('/create'),
            'view' => ViewProductionOrder::route('/{record}'),
            'edit' => EditProductionOrder::route('/{record}/edit'),
        ];
    }

    public static function daysLeftText(ProductionOrder $order): string
    {
        if (! $order->status->isOpen()) {
            return '-';
        }

        $days = $order->daysLeft();

        return match (true) {
            $days < 0 => 'Terlambat '.abs($days).' hari',
            $days === 0 => 'Hari ini',
            default => $days.' hari lagi',
        };
    }

    public static function daysLeftColor(ProductionOrder $order): string
    {
        if (! $order->status->isOpen()) {
            return 'gray';
        }

        $days = $order->daysLeft();

        return match (true) {
            $days < 0 => 'danger',
            $days <= ProductionOrder::NEAR_DEADLINE_DAYS => 'warning',
            default => 'gray',
        };
    }

    public static function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Active customers, plus the one already on the order even if it was deactivated later.
     *
     * @return array<int, string>
     */
    protected static function customerOptions(?ProductionOrder $record): array
    {
        return Customer::query()
            ->where('is_active', true)
            ->when($record?->customer_id, fn (Builder $query, int $id) => $query->orWhere('id', $id))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => $customer->is_active ? $customer->name : $customer->name.' (nonaktif)',
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function productOptions(?ProductionOrder $record): array
    {
        return Product::query()
            ->where('is_active', true)
            ->when($record?->product_id, fn (Builder $query, int $id) => $query->orWhere('id', $id))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->is_active ? $product->name : $product->name.' (nonaktif)',
            ])
            ->all();
    }
}
