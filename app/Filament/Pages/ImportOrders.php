<?php

namespace App\Filament\Pages;

use App\Enums\ImportMode;
use App\Models\ProductionOrder;
use App\Services\Export\OrderSheetWriter;
use App\Services\Import\ImportReport;
use App\Services\Import\OrderImporter;
use App\Services\Import\OrderSheetReader;
use App\Services\Import\RowResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ImportOrders extends Page
{
    private const SAMPLE_LIMIT = 10;

    protected static ?string $slug = 'impor-order';

    protected static ?string $title = 'Impor Order';

    protected static ?string $navigationLabel = 'Impor Order';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 30;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * What the last check found, as plain values so the page can show it.
     *
     * @var array<string, mixed>
     */
    public array $preview = [];

    /**
     * The summary of the last import that was saved.
     *
     * @var array<string, int>|null
     */
    public ?array $result = null;

    /**
     * The rows that were left out of the last saved import, kept on screen so they can be corrected and tried again.
     *
     * @var array<int, array<string, string>>
     */
    public array $leftOut = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('import', ProductionOrder::class) ?? false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pindahkan order dari Excel tanpa mengetik ulang. Unduh templat, isi, unggah, periksa hasilnya, lalu impor.';
    }

    #[Unexposed]
    public function mount(): void
    {
        $this->form->fill(['mode' => ImportMode::AddOnly->value]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Unduh templat')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => $this->downloadTemplate()),
        ];
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('file')
                    ->label('Berkas Excel')
                    ->helperText('Format .xlsx, paling banyak 1.000 baris dan 5 MB. Pakai templat dari tombol Unduh templat.')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(5120)
                    ->storeFiles(false)
                    ->required(),
                Radio::make('mode')
                    ->label('Cara mengimpor')
                    ->options(ImportMode::options())
                    ->default(ImportMode::AddOnly->value)
                    ->required(),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            $this->previewSection(),
            $this->resultSection(),
        ]);
    }

    #[Unexposed]
    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('check')
            ->footer([
                Actions::make([
                    Action::make('check')
                        ->label('Periksa berkas')
                        ->submit('check'),
                    Action::make('import')
                        ->label('Impor sekarang')
                        ->color('success')
                        ->visible(fn (): bool => (bool) ($this->preview['can_import'] ?? false))
                        ->requiresConfirmation()
                        ->modalHeading('Impor order sekarang?')
                        ->modalDescription(fn (): string => sprintf(
                            'Akan ditambah %d order dan diperbarui %d order. Baris yang bermasalah dan yang dilewati tidak ikut. Semua disimpan sekaligus: bila ada yang gagal, tidak ada yang tersimpan.',
                            $this->preview['created'] ?? 0,
                            $this->preview['updated'] ?? 0,
                        ))
                        ->modalSubmitActionLabel('Ya, impor')
                        ->action(fn () => $this->import()),
                ])->key('form-actions'),
            ]);
    }

    protected function previewSection(): Section
    {
        return Section::make('Hasil pemeriksaan')
            ->visible(fn (): bool => $this->preview !== [])
            ->columns(4)
            ->schema([
                TextEntry::make('file_errors')
                    ->label('Masalah pada berkas')
                    ->state(fn (): array => $this->preview['file_errors'] ?? [])
                    ->listWithLineBreaks()
                    ->color('danger')
                    ->columnSpanFull()
                    ->visible(fn (): bool => ($this->preview['file_errors'] ?? []) !== []),
                TextEntry::make('created')->label('Akan ditambah')->state(fn (): int => $this->preview['created'] ?? 0)->badge()->color('success'),
                TextEntry::make('updated')->label('Akan diperbarui')->state(fn (): int => $this->preview['updated'] ?? 0)->badge()->color('info'),
                TextEntry::make('skipped')->label('Dilewati')->state(fn (): int => $this->preview['skipped'] ?? 0)->badge()->color('gray'),
                TextEntry::make('rejected')->label('Bermasalah')->state(fn (): int => $this->preview['rejected'] ?? 0)->badge()->color('danger'),
                TextEntry::make('notes')
                    ->label('Catatan')
                    ->state(fn (): array => $this->preview['notes'] ?? [])
                    ->listWithLineBreaks()
                    ->columnSpanFull()
                    ->visible(fn (): bool => ($this->preview['notes'] ?? []) !== []),
                RepeatableEntry::make('issues')
                    ->label('Baris yang tidak akan diimpor')
                    ->state(fn (): array => $this->preview['issues'] ?? [])
                    ->contained(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->visible(fn (): bool => ($this->preview['issues'] ?? []) !== [])
                    ->schema([
                        TextEntry::make('row')->label('Baris'),
                        TextEntry::make('kind')->label('Keputusan')->badge()->color(fn (string $state): string => $state === 'Ditolak' ? 'danger' : 'gray'),
                        TextEntry::make('number')->label('Nomor order'),
                        TextEntry::make('reason')->label('Alasan'),
                    ]),
                RepeatableEntry::make('sample')
                    ->label('Baris yang akan diimpor')
                    ->state(fn (): array => $this->preview['sample'] ?? [])
                    ->contained(false)
                    ->columns(5)
                    ->columnSpanFull()
                    ->visible(fn (): bool => ($this->preview['sample'] ?? []) !== [])
                    ->schema([
                        TextEntry::make('row')->label('Baris'),
                        TextEntry::make('action')->label('Tindakan')->badge()->color(fn (string $state): string => $state === 'Tambah' ? 'success' : 'info'),
                        TextEntry::make('customer')->label('Pelanggan'),
                        TextEntry::make('product')->label('Produk'),
                        TextEntry::make('detail')->label('Jumlah dan tenggat'),
                    ]),
                TextEntry::make('more')
                    ->label('')
                    ->state(fn (): string => 'Dan '.($this->preview['more'] ?? 0).' baris lainnya yang juga akan diimpor.')
                    ->columnSpanFull()
                    ->visible(fn (): bool => ($this->preview['more'] ?? 0) > 0),
            ]);
    }

    protected function resultSection(): Section
    {
        return Section::make('Hasil impor')
            ->visible(fn (): bool => $this->result !== null)
            ->columns(4)
            ->schema([
                TextEntry::make('r_created')->label('Ditambah')->state(fn (): int => $this->result['created'] ?? 0)->badge()->color('success'),
                TextEntry::make('r_updated')->label('Diperbarui')->state(fn (): int => $this->result['updated'] ?? 0)->badge()->color('info'),
                TextEntry::make('r_skipped')->label('Dilewati')->state(fn (): int => $this->result['skipped'] ?? 0)->badge()->color('gray'),
                TextEntry::make('r_rejected')->label('Ditolak')->state(fn (): int => $this->result['rejected'] ?? 0)->badge()->color('danger'),
                RepeatableEntry::make('left_out')
                    ->label('Baris yang tidak diimpor. Perbaiki di Excel lalu unggah lagi.')
                    ->state(fn (): array => $this->leftOut)
                    ->contained(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->leftOut !== [])
                    ->schema([
                        TextEntry::make('row')->label('Baris'),
                        TextEntry::make('kind')->label('Keputusan')->badge()->color(fn (string $state): string => $state === 'Ditolak' ? 'danger' : 'gray'),
                        TextEntry::make('number')->label('Nomor order'),
                        TextEntry::make('reason')->label('Alasan'),
                    ]),
            ]);
    }

    #[Exposed]
    public function check(): void
    {
        abort_unless(static::canAccess(), 403);

        $report = $this->analyseUpload();

        $this->result = null;
        $this->leftOut = [];
        $this->preview = $this->describe($report);

        if ($report->fileErrors !== []) {
            Notification::make()->title('Berkas tidak bisa diperiksa')->body($report->fileErrors[0])->danger()->send();
        } elseif ($report->rejected() > 0) {
            Notification::make()->title($report->rejected().' baris bermasalah')->body('Baris itu tidak akan diimpor. Baris lain yang benar tetap bisa diimpor.')->warning()->send();
        } else {
            Notification::make()->title('Berkas siap diimpor')->success()->send();
        }
    }

    public function import(): void
    {
        abort_unless(static::canAccess(), 403);

        $report = $this->analyseUpload();

        if (! $report->canImport()) {
            $this->preview = $this->describe($report);

            Notification::make()->title('Tidak ada baris yang bisa diimpor')->warning()->send();

            return;
        }

        try {
            $counts = app(OrderImporter::class)->commit($report, auth()->user());
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Impor dibatalkan')
                ->body('Terjadi kesalahan saat menyimpan, jadi tidak ada data yang tersimpan. Periksa berkas lalu coba lagi.')
                ->danger()
                ->send();

            return;
        }

        $this->result = [
            'created' => $counts['created'],
            'updated' => $counts['updated'],
            'skipped' => $report->skipped(),
            'rejected' => $report->rejected(),
        ];
        $this->leftOut = $this->describe($report)['issues'];
        $this->preview = [];
        $this->form->fill(['file' => null, 'mode' => $this->data['mode'] ?? ImportMode::AddOnly->value]);

        Notification::make()
            ->title('Impor selesai')
            ->body("Ditambah {$counts['created']}, diperbarui {$counts['updated']}.")
            ->success()
            ->send();
    }

    protected function downloadTemplate(): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');

        app(OrderSheetWriter::class)->writeTemplate($path);

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, 'templat-order.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Reads the uploaded file again every time, so what is saved always comes from the file and never from page state.
     */
    protected function analyseUpload(): ImportReport
    {
        $state = $this->form->getState();
        $file = Arr::first(Arr::wrap($state['file'] ?? null));

        if (! $file instanceof TemporaryUploadedFile) {
            return new ImportReport(['Pilih berkas Excel lebih dulu.']);
        }

        $read = app(OrderSheetReader::class)->read($file->getRealPath());

        if ($read['errors'] !== []) {
            return new ImportReport($read['errors']);
        }

        return app(OrderImporter::class)->analyze($read['rows'], ImportMode::from($state['mode']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(ImportReport $report): array
    {
        $issues = [];
        $sample = [];
        $importable = 0;

        foreach ($report->rows as $row) {
            if (! $row->isImportable()) {
                $issues[] = [
                    'row' => (string) $row->row,
                    'kind' => $row->action === RowResult::ERROR ? 'Ditolak' : 'Dilewati',
                    'number' => $row->number ?? '-',
                    'reason' => implode(' ', $row->messages),
                ];

                continue;
            }

            $importable++;

            if (count($sample) < self::SAMPLE_LIMIT) {
                $sample[] = [
                    'row' => (string) $row->row,
                    'action' => $row->action === RowResult::CREATE ? 'Tambah' : 'Perbarui',
                    'customer' => $row->data['customer_name'],
                    'product' => $row->data['product_name'],
                    'detail' => $row->data['quantity'].' pasang, tenggat '.date('d/m/Y', strtotime($row->data['deadline_date'])),
                ];
            }
        }

        return [
            'file_errors' => $report->fileErrors,
            'created' => $report->created(),
            'updated' => $report->updated(),
            'skipped' => $report->skipped(),
            'rejected' => $report->rejected(),
            'notes' => $report->notes,
            'issues' => $issues,
            'sample' => $sample,
            'more' => max(0, $importable - count($sample)),
            'can_import' => $report->canImport(),
        ];
    }
}
