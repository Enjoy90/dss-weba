<?php

namespace App\Filament\Pages;

use App\Enums\RankGroup;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Services\Evaluation\EvaluationBlockedException;
use App\Services\Evaluation\EvaluationNeedsConfirmationException;
use App\Services\Evaluation\EvaluationPreflight;
use App\Services\Evaluation\EvaluationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Where the production manager starts a calculation (PRD F10). It shows whether the data is ready,
 * asks for confirmation when orders are incomplete, and lists the ranking of the latest result.
 * The full result screens (list, detail tabs, approval) come in Phase 7.
 */
class RunEvaluation extends Page implements HasTable
{
    use InteractsWithTable;

    private const LIST_LIMIT = 8;

    protected static ?string $slug = 'jalankan-perhitungan';

    protected static ?string $title = 'Jalankan Perhitungan';

    protected static ?string $navigationLabel = 'Jalankan Perhitungan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Prioritas';

    protected static ?int $navigationSort = 4;

    protected ?EvaluationPreflight $preflightMemo = null;

    protected ?Evaluation $latestMemo = null;

    protected bool $latestLoaded = false;

    protected ?bool $hasEarlierMemo = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create', Evaluation::class) ?? false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Menghitung peringkat semua order Antri dan Dikerjakan dengan metode SAW. Setiap perhitungan disimpan sebagai satu evaluasi yang tidak bisa diubah.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->label('Jalankan Perhitungan')
                ->icon(Heroicon::OutlinedPlay)
                ->disabled(fn (): bool => ! $this->preflight()->canRun())
                ->modalHeading('Jalankan perhitungan prioritas')
                ->modalDescription(fn (): Htmlable => $this->modalText())
                ->modalSubmitActionLabel('Hitung sekarang')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama evaluasi (boleh dikosongkan)')
                        ->placeholder('Bila kosong, memakai tanggal dan jam')
                        ->maxLength(150),
                    Textarea::make('notes')
                        ->label('Catatan (boleh dikosongkan)')
                        ->rows(2)
                        ->maxLength(500),
                    Checkbox::make('confirm')
                        ->label('Saya sudah membaca daftar di atas dan tetap ingin menghitung.')
                        ->accepted()
                        ->validationMessages(['accepted' => 'Centang dulu untuk melanjutkan, atau tutup dan lengkapi nilainya lebih dulu.'])
                        ->visible(fn (): bool => $this->preflight()->hasWarnings()),
                ])
                ->action(fn (array $data) => $this->run($data)),
        ];
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->readinessSection(),
            EmbeddedTable::make(),
        ]);
    }

    protected function readinessSection(): Section
    {
        return Section::make('Kesiapan data')
            ->description(fn (): string => $this->preflight()->canRun()
                ? 'Data siap dihitung. Nilai otomatis (tenggat, urgensi, nilai order, lama menunggu) dihitung ulang saat tombol ditekan.'
                : 'Perhitungan belum bisa dijalankan.')
            ->columns(4)
            ->schema([
                TextEntry::make('blockers')
                    ->label('Yang perlu diperbaiki lebih dulu')
                    ->state(fn (): array => $this->preflight()->blockers)
                    ->listWithLineBreaks()
                    ->color('danger')
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->preflight()->blockers !== []),
                TextEntry::make('order_count')
                    ->label('Order yang dihitung')
                    ->state(fn (): int => $this->preflight()->orderCount())
                    ->badge()
                    ->color('info'),
                TextEntry::make('incomplete_count')
                    ->label('Belum lengkap')
                    ->state(fn (): int => count($this->preflight()->incomplete))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                TextEntry::make('excluded_count')
                    ->label('Dikecualikan')
                    ->state(fn (): int => count($this->preflight()->excluded))
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => $this->preflight()->excluded !== []),
                TextEntry::make('stale_count')
                    ->label('Perlu diperbarui')
                    ->state(fn (): int => count($this->preflight()->stale))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                TextEntry::make('criteria_count')
                    ->label('Kriteria aktif')
                    ->state(fn (): int => $this->preflight()->criteria->count())
                    ->badge()
                    ->color('gray'),
                TextEntry::make('excluded_hint')
                    ->hiddenLabel()
                    ->state('Order yang belum lengkap dikecualikan dari perhitungan sesuai pengaturan. Order itu tidak mendapat peringkat sampai nilainya dilengkapi di menu Penilaian Order.')
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->preflight()->excluded !== []),
                TextEntry::make('warning_hint')
                    ->hiddenLabel()
                    ->state('Order yang belum lengkap tetap dihitung dengan nilai terburuk (skala 1) untuk kriteria yang kosong. Lengkapi dulu di menu Penilaian Order bila ingin hasil yang lebih tepat.')
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->preflight()->hasWarnings()),
            ]);
    }

    #[Unexposed]
    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => EvaluationItem::query()->where('evaluation_id', $this->latest()?->id ?? 0))
            ->heading(fn (): string => $this->latest() === null ? 'Hasil perhitungan terakhir' : 'Hasil terakhir: '.$this->latest()->name)
            ->description(fn (): ?string => $this->latest() === null ? null : sprintf(
                'Dihitung %s oleh %s, %d order, status %s. Halaman evaluasi lengkap menyusul di fase berikutnya.',
                $this->latest()->evaluated_at->format('d/m/Y H:i'),
                $this->latest()->evaluator?->name ?? 'sistem',
                $this->latest()->order_count,
                $this->latest()->status->label(),
            ))
            ->defaultSort('final_rank')
            ->columns([
                TextColumn::make('final_rank')
                    ->label('Peringkat')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('system_rank')
                    ->label('Sistem')
                    ->tooltip('Peringkat murni dari skor, sebelum kunci dan eskalasi')
                    ->sortable(),
                TextColumn::make('order')
                    ->label('Order')
                    ->state(fn (EvaluationItem $record): string => $record->order_snapshot['number'])
                    ->description(fn (EvaluationItem $record): string => $record->order_snapshot['customer']),
                TextColumn::make('deadline')
                    ->label('Tenggat')
                    ->state(fn (EvaluationItem $record): string => date('d/m/Y', strtotime($record->order_snapshot['deadline_date']))),
                TextColumn::make('waiting_days')
                    ->label('Menunggu')
                    ->suffix(' hari')
                    ->sortable(),
                TextColumn::make('score')
                    ->label('Skor')
                    ->numeric(4, ',', '.')
                    ->sortable(),
                TextColumn::make('rank_group')
                    ->label('Kelompok')
                    ->badge()
                    ->formatStateUsing(fn (RankGroup $state): string => $state->label())
                    ->color(fn (RankGroup $state): string => $state->color()),
                TextColumn::make('change')
                    ->label('Perubahan')
                    ->badge()
                    ->state(fn (EvaluationItem $record): string => $this->changeLabel($record))
                    ->color(fn (EvaluationItem $record): string => match (true) {
                        $record->rankChange() === null => $this->hasEarlierEvaluation() ? 'info' : 'gray',
                        $record->rankChange() > 0 => 'success',
                        $record->rankChange() < 0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada hasil perhitungan')
            ->emptyStateDescription('Tekan Jalankan Perhitungan di kanan atas. Peringkat order akan tampil di sini.')
            ->emptyStateIcon(Heroicon::OutlinedCalculator);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function run(array $data): void
    {
        Gate::authorize('create', Evaluation::class);

        try {
            $evaluation = app(EvaluationService::class)->run(
                auth()->user(),
                name: $data['name'] ?? null,
                notes: $data['notes'] ?? null,
                acknowledgeWarnings: (bool) ($data['confirm'] ?? false),
            );
        } catch (EvaluationBlockedException $exception) {
            Notification::make()
                ->title('Perhitungan belum bisa dijalankan')
                ->body(implode(' ', $exception->reasons))
                ->danger()
                ->persistent()
                ->send();

            return;
        } catch (EvaluationNeedsConfirmationException $exception) {
            Notification::make()
                ->title('Ada order yang belum lengkap')
                ->body($exception->getMessage())
                ->warning()
                ->persistent()
                ->send();

            return;
        } finally {
            $this->preflightMemo = null;
            $this->latestLoaded = false;
            $this->hasEarlierMemo = null;
        }

        $top = $evaluation->items()->first();

        Notification::make()
            ->title('Perhitungan selesai')
            ->body(sprintf('%d order dihitung. Peringkat 1: %s.', $evaluation->order_count, $top?->order_snapshot['number'] ?? '-'))
            ->success()
            ->send();
    }

    protected function preflight(): EvaluationPreflight
    {
        return $this->preflightMemo ??= app(EvaluationService::class)->preflight();
    }

    protected function latest(): ?Evaluation
    {
        if (! $this->latestLoaded) {
            $this->latestMemo = Evaluation::query()->with('evaluator')->latest('evaluated_at')->latest('id')->first();
            $this->latestLoaded = true;
        }

        return $this->latestMemo;
    }

    protected function hasEarlierEvaluation(): bool
    {
        return $this->hasEarlierMemo ??= $this->latest()?->hasEarlier() ?? false;
    }

    protected function changeLabel(EvaluationItem $item): string
    {
        $change = $item->rankChange();

        // The very first evaluation has nothing to compare with, so it shows a dash instead of "new" on every row.
        if ($change === null && ! $this->hasEarlierEvaluation()) {
            return '-';
        }

        return match (true) {
            $change === null => 'Baru',
            $change > 0 => 'Naik '.$change,
            $change < 0 => 'Turun '.abs($change),
            default => 'Tetap',
        };
    }

    /**
     * What the dialog says: the size of the job and, when needed, which orders are not complete.
     */
    protected function modalText(): Htmlable
    {
        $preflight = $this->preflight();

        $html = '<p>'.e(sprintf('%d order akan dihitung dengan %d kriteria aktif.', $preflight->orderCount(), $preflight->criteria->count())).'</p>';

        if ($preflight->incomplete !== []) {
            $html .= $this->orderList('Belum lengkap (kriteria yang kosong dihitung sebagai skala 1)', $preflight->incomplete);
        }

        if ($preflight->excluded !== []) {
            $html .= $this->orderList('Dikecualikan karena belum lengkap (tidak mendapat peringkat)', $preflight->excluded);
        }

        if ($preflight->stale !== []) {
            $html .= $this->orderList('Perlu diperbarui (nilai manual sudah lama)', $preflight->stale);
        }

        return new HtmlString($html);
    }

    /**
     * @param  array<int, array{order_id: int, number: string, criteria: array<int, string>}>  $orders
     */
    protected function orderList(string $title, array $orders): string
    {
        $items = '';

        foreach (array_slice($orders, 0, self::LIST_LIMIT) as $order) {
            $items .= '<li>'.e($order['number'].': '.implode(', ', $order['criteria'])).'</li>';
        }

        $more = count($orders) - self::LIST_LIMIT;

        if ($more > 0) {
            $items .= '<li>'.e("Dan {$more} order lainnya.").'</li>';
        }

        return '<p style="margin-top:0.75rem"><strong>'.e($title).'</strong></p><ul style="list-style:disc;padding-left:1.25rem">'.$items.'</ul>';
    }
}
