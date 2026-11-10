<?php

namespace App\Filament\Pages;

use App\Models\Evaluation;
use App\Services\Evaluation\EvaluationComparison;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Shows how every order moved between two evaluations. It is opened from the evaluation list and the
 * evaluation page, so it has no menu entry of its own.
 *
 * @property-read Schema $form
 */
class CompareEvaluations extends Page
{
    protected static ?string $slug = 'bandingkan-evaluasi';

    protected static ?string $title = 'Bandingkan Evaluasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Prioritas';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * The chosen evaluations: "from" is the earlier one and "to" the one it is compared with.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Evaluation::class) ?? false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pilih dua evaluasi untuk melihat order yang naik, turun, atau tetap pada peringkat akhir.';
    }

    #[Unexposed]
    public function mount(): void
    {
        $this->form->fill([
            'from' => request()->integer('a') ?: null,
            'to' => request()->integer('b') ?: null,
        ]);
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Select::make('from')
                    ->label('Dari evaluasi (sebelum)')
                    ->options(fn (): array => $this->options())
                    ->native(false)
                    ->searchable()
                    ->live(),
                Select::make('to')
                    ->label('Ke evaluasi (sesudah)')
                    ->options(fn (): array => $this->options())
                    ->native(false)
                    ->searchable()
                    ->live(),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Evaluasi yang dibandingkan')
                ->schema([EmbeddedSchema::make('form')]),
            Section::make('Hasil perbandingan')
                ->description(fn (): ?string => $this->summary())
                ->schema([
                    View::make('filament.evaluations.table')->viewData(fn (): array => $this->tableData()),
                ]),
        ]);
    }

    protected function selected(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return filled($value) ? (int) $value : null;
    }

    /**
     * @return array<int, string>
     */
    protected function options(): array
    {
        return Evaluation::query()
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function (Evaluation $evaluation): array {
                $stamp = $evaluation->evaluated_at->format('d/m/Y H:i');
                $name = str_contains((string) $evaluation->name, $stamp) ? $evaluation->name : $evaluation->name.' ('.$stamp.')';

                return [$evaluation->id => $name.', '.$evaluation->status->label()];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(): array
    {
        $fromId = $this->selected('from');
        $toId = $this->selected('to');

        if ($fromId === null || $toId === null || $fromId === $toId) {
            return [];
        }

        $from = Evaluation::query()->find($fromId);
        $to = Evaluation::query()->find($toId);

        return $from === null || $to === null ? [] : app(EvaluationComparison::class)->compare($from, $to);
    }

    protected function summary(): ?string
    {
        $rows = $this->rows();

        if ($rows === []) {
            return null;
        }

        $counts = app(EvaluationComparison::class)->counts($rows);

        return sprintf(
            '%d naik, %d turun, %d tetap, %d baru di evaluasi sesudah, %d tidak ada lagi.',
            $counts[EvaluationComparison::UP],
            $counts[EvaluationComparison::DOWN],
            $counts[EvaluationComparison::SAME],
            $counts[EvaluationComparison::NEW],
            $counts[EvaluationComparison::GONE],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function tableData(): array
    {
        $empty = match (true) {
            $this->selected('from') === null || $this->selected('to') === null => 'Pilih evaluasi "sebelum" dan "sesudah" di atas.',
            $this->selected('from') === $this->selected('to') => 'Pilih dua evaluasi yang berbeda.',
            default => 'Kedua evaluasi tidak memuat order.',
        };

        $rows = array_map(fn (array $row): array => [
            $row['number'].' ('.$row['customer'].')',
            $row['from_rank'] === null ? '-' : (string) $row['from_rank'],
            $row['to_rank'] === null ? '-' : (string) $row['to_rank'],
            $this->kindLabel($row),
            $row['from_score'] === null ? '-' : number_format($row['from_score'], 4, ',', '.'),
            $row['to_score'] === null ? '-' : number_format($row['to_score'], 4, ',', '.'),
        ], $this->rows());

        return [
            'headings' => ['Order', 'Peringkat sebelum', 'Peringkat sesudah', 'Perubahan', 'Skor sebelum', 'Skor sesudah'],
            'rows' => $rows,
            'empty' => $empty,
            'textual' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function kindLabel(array $row): string
    {
        return match ($row['kind']) {
            EvaluationComparison::UP => 'Naik '.$row['change'],
            EvaluationComparison::DOWN => 'Turun '.abs($row['change']),
            EvaluationComparison::SAME => 'Tetap',
            EvaluationComparison::NEW => 'Baru di evaluasi sesudah',
            default => 'Tidak ada di evaluasi sesudah',
        };
    }
}
