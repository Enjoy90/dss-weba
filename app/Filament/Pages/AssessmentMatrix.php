<?php

namespace App\Filament\Pages;

use App\Enums\AssessmentSource;
use App\Enums\Completeness;
use App\Enums\ValueSource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Services\AssessmentFormatter;
use App\Services\AssessmentResolver;
use App\Services\AssessmentWriter;
use App\Services\ResolvedValue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Matrix of the open orders (rows) against the active criteria (columns). Every cell shows the raw value,
 * the 1 to 5 score it converts to and where the value comes from.
 */
class AssessmentMatrix extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'penilaian-order';

    protected static ?string $title = 'Penilaian Order';

    protected static ?string $navigationLabel = 'Penilaian Order';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 20;

    /**
     * @var array<int, Collection<int, ResolvedValue>>
     */
    protected array $resolvedMemo = [];

    protected ?Collection $activeCriteria = null;

    protected ?AssessmentResolver $resolverInstance = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Assessment::class) ?? false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Nilai otomatis diambil dari data order dan selalu terbaru. Klik nilai manual untuk mengubahnya. Tanda segitiga berarti nilai sudah lama dan perlu diperbarui.';
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    #[Unexposed]
    public function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('number')
                ->label('Nomor')
                ->searchable()
                ->sortable(),
            TextColumn::make('customer.name')
                ->label('Pelanggan')
                ->searchable(),
            TextColumn::make('completeness')
                ->label('Kelengkapan')
                ->badge()
                ->state(fn (ProductionOrder $record): string => $this->completenessOf($record)->label())
                ->color(fn (ProductionOrder $record): string => $this->completenessOf($record)->color()),
        ];

        foreach ($this->visibleCriteria() as $criterion) {
            $columns[] = $this->criterionColumn($criterion);
        }

        return $table
            ->query(ProductionOrder::query()->open()->with(['customer', 'assessments.updater']))
            ->columns($columns)
            ->defaultSort('deadline_date')
            ->filters([
                Filter::make('incomplete')
                    ->label('Belum lengkap')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereKey(
                        $this->resolver()->orderIdsWith(Completeness::Incomplete, $this->allActiveCriteria()),
                    )),
                Filter::make('stale')
                    ->label('Perlu diperbarui')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereKey(
                        $this->resolver()->orderIdsWith(Completeness::Stale, $this->allActiveCriteria()),
                    )),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                Action::make('openOrder')
                    ->label('Buka order')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->tooltip('Buka order')
                    ->url(fn (ProductionOrder $record): string => ProductionOrderResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Belum ada order yang dinilai')
            ->emptyStateDescription('Order berstatus Antri atau Dikerjakan tampil di sini. Tambahkan order lebih dulu di menu Order Produksi.');
    }

    protected function criterionColumn(Criterion $criterion): TextColumn
    {
        return TextColumn::make('c_'.$criterion->id)
            ->label($criterion->code)
            ->extraHeaderAttributes(['title' => $criterion->name])
            ->state(fn (ProductionOrder $record): string => AssessmentFormatter::raw($criterion, $this->valueOf($record, $criterion)->raw))
            ->description(fn (ProductionOrder $record): string => $this->describe($this->valueOf($record, $criterion)))
            ->color(fn (ProductionOrder $record): ?string => $this->cellColor($this->valueOf($record, $criterion)))
            ->icon(fn (ProductionOrder $record): ?Heroicon => $this->cellIcon($this->valueOf($record, $criterion)))
            ->action($this->cellAction($criterion));
    }

    protected function cellAction(Criterion $criterion): Action
    {
        return Action::make('edit_'.$criterion->id)
            ->label('Ubah nilai '.$criterion->code)
            ->modalHeading(fn (ProductionOrder $record): string => 'Nilai '.$criterion->code.' untuk '.$record->number)
            ->modalDescription($criterion->name.($criterion->unit ? ' ('.$criterion->unit.')' : ''))
            ->modalSubmitActionLabel('Simpan nilai')
            ->hidden(fn (ProductionOrder $record): bool => ! $this->canEditCell($record, $criterion))
            ->fillForm(fn (ProductionOrder $record): array => $this->formState($record, $criterion))
            ->schema(fn (ProductionOrder $record): array => $this->formSchema($record, $criterion))
            ->action(fn (ProductionOrder $record, array $data) => $this->saveCell($record, $criterion, $data));
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(ProductionOrder $order, Criterion $criterion): array
    {
        if ($criterion->value_source === ValueSource::CustomerUrgency) {
            return [
                TextEntry::make('customer_urgency')
                    ->label('Urgensi pelanggan saat ini')
                    ->state(Customer::URGENCY_LABELS[$order->customer->urgency_level] ?? (string) $order->customer->urgency_level),
                Toggle::make('override')
                    ->label('Timpa urgensi pelanggan untuk order ini')
                    ->helperText('Matikan untuk kembali memakai urgensi pelanggan.')
                    ->live(),
                Select::make('value')
                    ->label('Urgensi untuk order ini')
                    ->options(Customer::URGENCY_LABELS)
                    ->native(false)
                    ->visible(fn (Get $get): bool => (bool) $get('override'))
                    ->required(fn (Get $get): bool => (bool) $get('override')),
                TextInput::make('reason')
                    ->label('Alasan menimpa')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) $get('override'))
                    ->required(fn (Get $get): bool => (bool) $get('override')),
            ];
        }

        $field = TextInput::make('value')
            ->label('Nilai')
            ->numeric()
            ->suffix($this->unitSuffix($criterion))
            ->helperText($this->lastUpdatedNote($order, $criterion));

        [$min, $max] = $this->bounds($criterion);

        if ($min !== null) {
            $field->minValue($min);
        }

        if ($max !== null) {
            $field->maxValue($max);
        }

        return [$field];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formState(ProductionOrder $order, Criterion $criterion): array
    {
        $resolved = $this->valueOf($order, $criterion);

        if ($criterion->value_source === ValueSource::CustomerUrgency) {
            return [
                'override' => $resolved->source === AssessmentSource::Override,
                'value' => $resolved->source === AssessmentSource::Override ? (int) $resolved->raw : null,
                'reason' => $resolved->overrideReason,
            ];
        }

        return ['value' => $resolved->raw];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveCell(ProductionOrder $order, Criterion $criterion, array $data): void
    {
        abort_unless($this->canEditCell($order, $criterion), 403);

        $user = auth()->user();
        $writer = app(AssessmentWriter::class);

        try {
            if ($criterion->value_source === ValueSource::CustomerUrgency) {
                if ($data['override'] ?? false) {
                    $writer->override($order, $criterion, (float) $data['value'], $data['reason'] ?? null, $user);
                } else {
                    $writer->clearOverride($order, $criterion, $user);
                }
            } else {
                $value = $data['value'] ?? null;

                $writer->setManual($order, $criterion, $value === null || $value === '' ? null : (float) $value, $user);
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Nilai tidak disimpan')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        $this->resolvedMemo = [];

        Notification::make()->title('Nilai '.$criterion->code.' disimpan')->success()->send();
    }

    protected function canEditCell(ProductionOrder $order, Criterion $criterion): bool
    {
        if (! Gate::allows('updateValue', [Assessment::class, $criterion])) {
            return false;
        }

        return $criterion->value_source === ValueSource::CustomerUrgency
            || app(AssessmentWriter::class)->isManualFor($order, $criterion);
    }

    protected function describe(ResolvedValue $value): string
    {
        $score = $value->score === null ? 'Skala -' : 'Skala '.$value->score;

        $source = match (true) {
            $value->source === AssessmentSource::Empty => 'belum diisi',
            $value->stale => 'manual, perlu diperbarui',
            default => strtolower($value->source->label()),
        };

        return $score.', '.$source;
    }

    protected function cellColor(ResolvedValue $value): ?string
    {
        return match (true) {
            $value->source === AssessmentSource::Empty => 'danger',
            $value->stale => 'warning',
            default => null,
        };
    }

    protected function cellIcon(ResolvedValue $value): ?Heroicon
    {
        return match (true) {
            $value->source === AssessmentSource::Empty => Heroicon::OutlinedExclamationCircle,
            $value->stale => Heroicon::OutlinedExclamationTriangle,
            default => null,
        };
    }

    protected function valueOf(ProductionOrder $order, Criterion $criterion): ResolvedValue
    {
        return $this->resolvedFor($order)->get($criterion->id);
    }

    protected function completenessOf(ProductionOrder $order): Completeness
    {
        return $this->resolver()->completeness($this->resolvedFor($order));
    }

    /**
     * @return Collection<int, ResolvedValue>
     */
    protected function resolvedFor(ProductionOrder $order): Collection
    {
        return $this->resolvedMemo[$order->id] ??= $this->resolver()->resolveAll($order, $this->allActiveCriteria());
    }

    /**
     * Every active criterion, used to judge completeness whatever the viewer is allowed to see.
     *
     * @return Collection<int, Criterion>
     */
    protected function allActiveCriteria(): Collection
    {
        return $this->activeCriteria ??= Criterion::query()->active()->ordered()->with('scales')->get();
    }

    /**
     * @return Collection<int, Criterion>
     */
    protected function visibleCriteria(): Collection
    {
        return $this->allActiveCriteria()
            ->filter(fn (Criterion $criterion): bool => Gate::allows('viewCriterion', [Assessment::class, $criterion]))
            ->values();
    }

    protected function resolver(): AssessmentResolver
    {
        return $this->resolverInstance ??= AssessmentResolver::make();
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function bounds(Criterion $criterion): array
    {
        return match ($criterion->unit) {
            'persen' => [0, 100],
            'jam', 'rupiah', 'hari' => [0, null],
            'tingkat' => [1, 5],
            default => [null, null],
        };
    }

    protected function unitSuffix(Criterion $criterion): ?string
    {
        return match ($criterion->unit) {
            'persen' => '%',
            'rupiah' => 'Rp',
            null, '' => null,
            default => $criterion->unit,
        };
    }

    protected function lastUpdatedNote(ProductionOrder $order, Criterion $criterion): ?string
    {
        $assessment = $order->assessments->firstWhere('criterion_id', $criterion->id);

        if ($assessment === null || $assessment->value_updated_at === null) {
            return 'Belum pernah diisi. Kosongkan lalu simpan untuk membiarkannya kosong.';
        }

        $who = $assessment->updater?->name;

        return 'Terakhir diperbarui '.$assessment->value_updated_at->translatedFormat('d M Y').($who ? ' oleh '.$who : '').'. Kosongkan lalu simpan untuk menghapus nilai.';
    }
}
