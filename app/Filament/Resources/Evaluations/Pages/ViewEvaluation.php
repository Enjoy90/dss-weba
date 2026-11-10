<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
use App\Enums\RankGroup;
use App\Filament\Pages\CompareEvaluations;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\Evaluations\EvaluationTables;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\User;
use App\Services\Evaluation\EvaluationAdjuster;
use App\Services\Evaluation\EvaluationWorkflow;
use App\Services\Evaluation\PriorityPlan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * One evaluation: a summary, the approval buttons, and seven tabs (PRD F11). The first tab is the ranking
 * with the manual adjustment, the others are read-only tables built from the copy saved with the evaluation.
 */
class ViewEvaluation extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = EvaluationResource::class;

    protected ?EvaluationTables $tablesMemo = null;

    protected ?PriorityPlan $planMemo = null;

    public function getTitle(): string
    {
        return $this->getRecord()->name ?? 'Evaluasi';
    }

    public function getSubheading(): ?string
    {
        return 'Hasil perhitungan yang tersimpan. Isinya tidak bisa diubah, hanya statusnya yang bergerak lewat tombol di kanan atas.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->flowAction('submit', 'Ajukan untuk disetujui', Heroicon::OutlinedPaperAirplane, fn (Evaluation $evaluation): bool => $evaluation->allowsSubmit())
                ->modalHeading('Ajukan evaluasi ini?')
                ->modalDescription('Pimpinan akan memeriksa dan menyetujui atau menolaknya. Peringkat masih bisa disesuaikan selama berstatus Diajukan.')
                ->modalSubmitActionLabel('Ya, ajukan')
                ->action(fn () => $this->perform(fn (User $user) => app(EvaluationWorkflow::class)->submit($this->getRecord(), $user), 'Evaluasi diajukan ke Pimpinan.')),
            $this->flowAction('approve', 'Setujui', Heroicon::OutlinedCheckCircle, fn (Evaluation $evaluation): bool => $evaluation->allowsDecision())
                ->color('success')
                ->modalHeading('Setujui evaluasi ini?')
                ->modalDescription('Setelah disetujui, Manajer atau Pimpinan bisa menerapkan prioritasnya.')
                ->modalSubmitActionLabel('Ya, setujui')
                ->action(fn () => $this->perform(fn (User $user) => app(EvaluationWorkflow::class)->approve($this->getRecord(), $user), 'Evaluasi disetujui.')),
            $this->flowAction('reject', 'Tolak', Heroicon::OutlinedXCircle, fn (Evaluation $evaluation): bool => $evaluation->allowsDecision())
                ->color('danger')
                ->modalHeading('Tolak evaluasi ini?')
                ->modalDescription('Evaluasi kembali ke Draf dan catatan Anda tampil di halaman ini. Manajer bisa memperbaiki lalu mengajukannya lagi.')
                ->modalSubmitActionLabel('Tolak evaluasi')
                ->schema([
                    Textarea::make('note')
                        ->label('Catatan penolakan')
                        ->helperText('Jelaskan apa yang perlu diperbaiki.')
                        ->required()
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->action(fn (array $data) => $this->perform(fn (User $user) => app(EvaluationWorkflow::class)->reject($this->getRecord(), $user, $data['note'] ?? null), 'Evaluasi ditolak dan dikembalikan ke Draf.')),
            $this->flowAction('apply', 'Terapkan prioritas', Heroicon::OutlinedFlag, fn (Evaluation $evaluation): bool => $evaluation->allowsApply())
                ->color('success')
                ->modalHeading('Terapkan prioritas dari evaluasi ini?')
                ->modalDescription(fn (): string => $this->applyDescription())
                ->modalSubmitActionLabel('Ya, terapkan')
                ->action(fn () => $this->perform(fn (User $user) => app(EvaluationWorkflow::class)->apply($this->getRecord(), $user), 'Prioritas diterapkan. Label Prioritas Utama sudah diperbarui di daftar order.')),
            Action::make('compare')
                ->label('Bandingkan')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->url(fn (): string => CompareEvaluations::getUrl(['a' => $this->getRecord()->id])),
            $this->flowAction('cancel', 'Batalkan evaluasi', Heroicon::OutlinedNoSymbol, fn (Evaluation $evaluation): bool => $evaluation->allowsCancel())
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Batalkan evaluasi ini?')
                ->modalDescription('Isi evaluasi tetap tersimpan, hanya statusnya menjadi Dibatalkan dan tidak bisa diterapkan lagi.')
                ->modalSubmitActionLabel('Ya, batalkan')
                ->action(fn () => $this->perform(fn (User $user) => app(EvaluationWorkflow::class)->cancel($this->getRecord(), $user), 'Evaluasi dibatalkan.')),
        ];
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->summarySection(),
            Callout::make('Evaluasi ini dikembalikan oleh Pimpinan')
                ->description(fn (): string => (string) $this->getRecord()->rejection_note)
                ->danger()
                ->visible(fn (): bool => filled($this->getRecord()->rejection_note) && $this->getRecord()->status === EvaluationStatus::Draft),
            Callout::make('Slot Prioritas Utama penuh')
                ->description(fn (): string => $this->slotWarning())
                ->warning()
                ->visible(fn (): bool => $this->plan()->isFull()),
            Tabs::make('Rincian')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Ringkasan dan peringkat')->schema([EmbeddedTable::make()]),
                    Tab::make('Data nilai asli')->schema([
                        $this->tableView(fn (EvaluationTables $tables): array => $tables->rawValues() + ['note' => $this->hiddenNote()]),
                    ]),
                    Tab::make('Matriks keputusan')->schema([
                        $this->tableView(fn (EvaluationTables $tables): array => $tables->scaleValues() + ['note' => 'Nilai skala 1 sampai 5 tiap order. Skala 5 paling didahulukan. '.$this->hiddenNote()]),
                    ]),
                    Tab::make('Matriks normalisasi')->schema([
                        $this->tableView(fn (EvaluationTables $tables): array => $tables->normalizedValues() + ['note' => 'Nilai skala dibagi nilai skala terbesar di kolom kriteria itu. '.$this->hiddenNote()]),
                    ]),
                    Tab::make('Perhitungan skor')->schema([
                        $this->tableView(fn (EvaluationTables $tables): array => $tables->weightedValues() + ['note' => 'Bobot dikali nilai normalisasi untuk tiap kriteria, lalu dijumlahkan menjadi skor V. '.$this->hiddenNote()]),
                    ]),
                    Tab::make('Kriteria dan skala')->schema([
                        $this->tableView(fn (EvaluationTables $tables): array => $tables->criteriaAndScales() + ['note' => 'Salinan kriteria, bobot, dan skala yang dipakai saat perhitungan ini dijalankan. Perubahan sesudahnya tidak memengaruhi evaluasi ini. '.$this->hiddenNote(), 'textual' => true]),
                    ]),
                    Tab::make('Peringatan')
                        ->badge(fn (): int => count($this->tables()->warnings()['rows']))
                        ->schema([
                            Callout::make('Slot Prioritas Utama penuh')
                                ->description(fn (): string => $this->slotWarning())
                                ->warning()
                                ->visible(fn (): bool => $this->plan()->isFull()),
                            $this->tableView(fn (EvaluationTables $tables): array => $tables->warnings() + [
                                'note' => 'Nilai kosong dihitung sebagai skala 1. Nilai manual yang sudah lama perlu diperbarui di menu Penilaian Order sebelum perhitungan berikutnya.',
                                'empty' => 'Tidak ada peringatan. Semua nilai terisi dan masih baru.',
                                'textual' => true,
                            ]),
                        ]),
                ]),
        ]);
    }

    #[Unexposed]
    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => EvaluationItem::query()->where('evaluation_id', $this->getRecord()->id))
            ->defaultSort('final_rank')
            ->columns([
                TextColumn::make('final_rank')
                    ->label('Peringkat')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('system_rank')
                    ->label('Sistem')
                    ->tooltip('Peringkat murni dari skor, sebelum kunci, eskalasi, dan penyesuaian')
                    ->sortable(),
                TextColumn::make('order')
                    ->label('Order')
                    ->state(fn (EvaluationItem $record): string => $record->order_snapshot['number'])
                    ->description(fn (EvaluationItem $record): string => $record->order_snapshot['customer'])
                    ->searchable(query: fn ($query, string $search) => $query->where('order_snapshot', 'like', '%'.$search.'%')),
                TextColumn::make('deadline')
                    ->label('Tenggat')
                    ->state(fn (EvaluationItem $record): string => date('d/m/Y', strtotime($record->order_snapshot['deadline_date'])))
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('priority_label')
                    ->label('Prioritas Utama')
                    ->badge()
                    ->state(fn (EvaluationItem $record): ?string => $this->plan()->has($record->production_order_id) ? 'Prioritas Utama' : null)
                    ->color('warning'),
                TextColumn::make('change')
                    ->label('Perubahan')
                    ->badge()
                    ->state(fn (EvaluationItem $record): string => $this->changeLabel($record))
                    ->color(fn (EvaluationItem $record): string => match (true) {
                        $record->rankChange() === null => $this->getRecord()->hasEarlier() ? 'info' : 'gray',
                        $record->rankChange() > 0 => 'success',
                        $record->rankChange() < 0 => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('adjustment_reason')
                    ->label('Disesuaikan')
                    ->badge()
                    ->state(fn (EvaluationItem $record): ?string => filled($record->adjustment_reason) ? 'Disesuaikan' : null)
                    ->tooltip(fn (EvaluationItem $record): ?string => $record->adjustment_reason)
                    ->color('info'),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                Action::make('adjust')
                    ->label('Sesuaikan peringkat')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->iconButton()
                    ->tooltip('Sesuaikan peringkat')
                    ->visible(fn (): bool => Gate::allows('adjust', $this->getRecord()) && $this->getRecord()->allowsAdjust())
                    ->modalHeading(fn (EvaluationItem $record): string => 'Sesuaikan peringkat '.$record->order_snapshot['number'])
                    ->modalDescription('Order dipindah ke peringkat yang Anda pilih, order lain bergeser. Peringkat sistem tetap tersimpan di sampingnya. Order terkunci tidak boleh turun ke bawah order biasa.')
                    ->modalSubmitActionLabel('Simpan peringkat')
                    ->fillForm(fn (EvaluationItem $record): array => ['rank' => $record->final_rank])
                    ->schema([
                        TextInput::make('rank')
                            ->label('Peringkat baru')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(fn (): int => max(1, $this->getRecord()->order_count))
                            ->required(),
                        Textarea::make('reason')
                            ->label('Alasan penyesuaian')
                            ->required()
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (EvaluationItem $record, array $data): void {
                        $this->perform(
                            fn (User $user) => app(EvaluationAdjuster::class)->move($record, (int) $data['rank'], $data['reason'] ?? null, $user),
                            'Peringkat '.$record->order_snapshot['number'].' disesuaikan.',
                        );
                    }),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Evaluasi ini tidak memuat order')
            ->emptyStateDescription('Tidak ada order yang dihitung pada evaluasi ini.');
    }

    protected function summarySection(): Section
    {
        $record = fn (): Evaluation => $this->getRecord();

        return Section::make('Ringkasan')
            ->columns(4)
            ->schema([
                TextEntry::make('status')
                    ->label('Status')
                    ->state(fn (): string => $record()->status->label())
                    ->badge()
                    ->color(fn (): string => $record()->status->color()),
                TextEntry::make('evaluated_at')
                    ->label('Waktu perhitungan')
                    ->state(fn (): string => $record()->evaluated_at->format('d/m/Y H:i')),
                TextEntry::make('trigger')
                    ->label('Pemicu')
                    ->state(fn (): string => $record()->trigger->label()),
                TextEntry::make('evaluator')
                    ->label('Dijalankan oleh')
                    ->state(fn (): string => $record()->evaluator?->name ?? ($record()->trigger === EvaluationTrigger::Scheduled ? 'Sistem (terjadwal)' : '-')),
                TextEntry::make('order_count')
                    ->label('Order dihitung')
                    ->state(fn (): int => $record()->order_count),
                TextEntry::make('top_n')
                    ->label('Jumlah Prioritas Utama')
                    ->state(fn (): string => (string) ($record()->top_n ?? '-')),
                TextEntry::make('submitted')
                    ->label('Diajukan')
                    ->state(fn (): string => $record()->submitted_at === null ? '-' : $record()->submitted_at->format('d/m/Y H:i')),
                TextEntry::make('approver')
                    ->label('Disetujui oleh')
                    ->state(fn (): string => $record()->approver === null ? '-' : $record()->approver->name.', '.$record()->approved_at->format('d/m/Y H:i')),
                TextEntry::make('applied')
                    ->label('Diterapkan')
                    ->state(fn (): string => $record()->applied_at === null ? '-' : $record()->applied_at->format('d/m/Y H:i')),
                TextEntry::make('notes')
                    ->label('Catatan')
                    ->state(fn (): string => $record()->notes ?? '-')
                    ->columnSpan(3),
            ]);
    }

    protected function flowAction(string $name, string $label, Heroicon $icon, callable $allows): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (): bool => Gate::allows($name, $this->getRecord()) && $allows($this->getRecord()));
    }

    /**
     * Runs one step, shows what went wrong in plain words, and reloads the evaluation.
     *
     * @param  callable(User): mixed  $change
     */
    protected function perform(callable $change, string $success): void
    {
        try {
            $change(auth()->user());
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Tidak bisa dilanjutkan')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        } catch (AuthorizationException) {
            Notification::make()->title('Anda tidak berhak melakukan ini')->danger()->send();

            return;
        } finally {
            $this->planMemo = null;
            $this->tablesMemo = null;
        }

        $this->record = $this->getRecord()->fresh();

        Notification::make()->title($success)->success()->send();
    }

    protected function applyDescription(): string
    {
        $plan = app(EvaluationWorkflow::class)->planFor($this->getRecord());
        $numbers = EvaluationItem::query()
            ->where('evaluation_id', $this->getRecord()->id)
            ->whereIn('production_order_id', $plan->topOrderIds)
            ->orderBy('final_rank')
            ->get()
            ->map(fn (EvaluationItem $item): string => $item->order_snapshot['number'])
            ->implode(', ');

        $text = $numbers === ''
            ? 'Tidak ada order Antri yang bisa diberi label. Semua label Prioritas Utama yang lama akan dilepas.'
            : 'Order berikut mendapat label Prioritas Utama: '.$numbers.'. Label dari evaluasi sebelumnya diganti dan order lain kembali Normal.';

        return $plan->isFull() ? $text.' Perhatian: '.$this->slotWarning() : $text;
    }

    protected function slotWarning(): string
    {
        $plan = $this->plan();

        return sprintf(
            'Order Antri yang dikunci manual atau dieskalasi berjumlah %d, sedangkan jumlah Prioritas Utama hanya %d. Semua slot terpakai oleh keduanya, jadi pesanan baru yang mendesak tidak kebagian slot. Pertimbangkan menaikkan jumlah Prioritas Utama atau melepas kunci yang tidak perlu.',
            $plan->reserved,
            $plan->slots,
        );
    }

    protected function changeLabel(EvaluationItem $item): string
    {
        $change = $item->rankChange();

        return match (true) {
            $change === null && ! $this->getRecord()->hasEarlier() => '-',
            $change === null => 'Baru',
            $change > 0 => 'Naik '.$change,
            $change < 0 => 'Turun '.abs($change),
            default => 'Tetap',
        };
    }

    protected function hiddenNote(): string
    {
        return $this->tables()->hidesCriteria() ? 'Kriteria nilai order tidak ditampilkan untuk peran Anda.' : '';
    }

    protected function tables(): EvaluationTables
    {
        return $this->tablesMemo ??= new EvaluationTables($this->getRecord(), auth()->user());
    }

    /**
     * The label plan as the saved evaluation shows it: queued status is read from the copy of the order.
     */
    protected function plan(): PriorityPlan
    {
        return $this->planMemo ??= PriorityPlan::for(
            $this->getRecord()->items()->get(),
            $this->getRecord()->top_n ?? 1,
            fn (EvaluationItem $item): bool => ($item->order_snapshot['status'] ?? null) === 'queued',
        );
    }

    /**
     * @param  callable(EvaluationTables): array<string, mixed>  $data
     */
    protected function tableView(callable $data): View
    {
        return View::make('filament.evaluations.table')
            ->viewData(fn (): array => $data($this->tables()));
    }
}
