<?php

namespace App\Filament\Pages;

use App\Models\Evaluation;
use App\Services\Report\ReportBuilder;
use App\Services\Report\ReportDocument;
use App\Services\Report\ReportPdf;
use App\Services\Report\ReportXlsx;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The reports of PRD F13. The user picks a report, sees it on the screen, and downloads it as PDF or Excel.
 * The preview and both files are drawn from the same ReportDocument, so the numbers are the same everywhere.
 *
 * @property-read Schema $form
 */
class Reports extends Page
{
    public const PREVIEW_ROWS = 50;

    public const TYPES = [
        'ranking' => 'Peringkat prioritas (per evaluasi)',
        'calculation' => 'Rincian perhitungan SAW (per evaluasi)',
        'late' => 'Order terlambat (per periode)',
        'recap' => 'Rekap order (per periode)',
        'history' => 'Riwayat evaluasi (per periode)',
    ];

    protected static ?string $slug = 'laporan';

    protected static ?string $title = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pilih laporan, lihat isinya di layar, lalu unduh sebagai PDF atau Excel.';
    }

    #[Unexposed]
    public function mount(): void
    {
        $this->form->fill([
            'type' => 'ranking',
            'evaluation_id' => $this->evaluations()->first()?->id,
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]);
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(3)
            ->components([
                Select::make('type')
                    ->label('Jenis laporan')
                    ->options(self::TYPES)
                    ->required()
                    ->native(false)
                    ->live(),
                Select::make('evaluation_id')
                    ->label('Evaluasi')
                    ->options(fn (): array => $this->evaluations()->mapWithKeys(fn (Evaluation $evaluation): array => [$evaluation->id => $evaluation->optionLabel()])->all())
                    ->searchable()
                    ->native(false)
                    ->placeholder('Belum ada evaluasi')
                    ->visible(fn (Get $get): bool => $this->needsEvaluation($get('type')))
                    ->columnSpan(2)
                    ->live(),
                DatePicker::make('from')
                    ->label('Dari tanggal')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->visible(fn (Get $get): bool => ! $this->needsEvaluation($get('type')))
                    ->live(),
                DatePicker::make('to')
                    ->label('Sampai tanggal')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->visible(fn (Get $get): bool => ! $this->needsEvaluation($get('type')))
                    ->live(),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pilih laporan')
                ->schema([EmbeddedSchema::make('form')]),
            Section::make('Pratinjau')
                ->description(fn (): string => 'Berkas PDF dan Excel memuat isi yang sama, dengan semua baris.')
                ->schema([
                    View::make('filament.reports.preview')->viewData(fn (): array => [
                        'document' => $this->document(),
                        'problem' => $this->problem(),
                        'limit' => self::PREVIEW_ROWS,
                    ]),
                ]),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Unduh PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('primary')
                ->action(fn (): ?StreamedResponse => $this->downloadPdf()),
            Action::make('downloadExcel')
                ->label('Unduh Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(fn (): ?BinaryFileResponse => $this->downloadExcel()),
        ];
    }

    /**
     * The report for the current choice, or null while the choice is incomplete or wrong.
     */
    public function document(): ?ReportDocument
    {
        if ($this->problem() !== null) {
            return null;
        }

        $user = auth()->user();
        $builder = app(ReportBuilder::class);
        $type = $this->data['type'] ?? null;

        if ($this->needsEvaluation($type)) {
            $evaluation = Evaluation::query()->find($this->data['evaluation_id'] ?? null);

            return match (true) {
                $evaluation === null => null,
                $type === 'ranking' => $builder->ranking($evaluation, $user),
                default => $builder->calculation($evaluation, $user),
            };
        }

        $from = Carbon::parse($this->data['from']);
        $to = Carbon::parse($this->data['to']);

        return match ($type) {
            'late' => $builder->lateOrders($from, $to, $user),
            'recap' => $builder->ordersRecap($from, $to, $user),
            'history' => $builder->evaluationHistory($from, $to, $user),
            default => null,
        };
    }

    /**
     * Why there is no report to show, in words for the user, or null when the choice is complete.
     */
    public function problem(): ?string
    {
        $type = $this->data['type'] ?? null;

        if (! array_key_exists((string) $type, self::TYPES)) {
            return 'Pilih jenis laporan terlebih dahulu.';
        }

        if ($this->needsEvaluation($type)) {
            if (blank($this->data['evaluation_id'] ?? null) || ! Evaluation::query()->whereKey($this->data['evaluation_id'])->exists()) {
                return $this->evaluations()->isEmpty()
                    ? 'Belum ada evaluasi. Laporan ini tersedia setelah perhitungan dijalankan.'
                    : 'Pilih evaluasi yang akan dilaporkan.';
            }

            return null;
        }

        if (blank($this->data['from'] ?? null) || blank($this->data['to'] ?? null)) {
            return 'Isi tanggal awal dan tanggal akhir periode.';
        }

        if (Carbon::parse($this->data['to'])->lt(Carbon::parse($this->data['from']))) {
            return 'Tanggal akhir tidak boleh sebelum tanggal awal.';
        }

        return null;
    }

    public function downloadPdf(): ?StreamedResponse
    {
        $document = $this->document();

        if ($document === null) {
            return null;
        }

        $content = app(ReportPdf::class)->render($document);

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $document->fileName('pdf'), ['Content-Type' => 'application/pdf']);
    }

    public function downloadExcel(): ?BinaryFileResponse
    {
        $document = $this->document();

        if ($document === null) {
            return null;
        }

        $directory = storage_path('app/private/reports');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid('laporan-', true).'.xlsx';

        app(ReportXlsx::class)->write($document, $path);

        return response()->download($path, $document->fileName('xlsx'))->deleteFileAfterSend();
    }

    protected function needsEvaluation(mixed $type): bool
    {
        return in_array($type, ['ranking', 'calculation'], true);
    }

    /**
     * @return Collection<int, Evaluation>
     */
    protected function evaluations()
    {
        return Evaluation::query()->orderByDesc('evaluated_at')->orderByDesc('id')->get();
    }
}
