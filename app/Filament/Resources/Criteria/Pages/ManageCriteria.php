<?php

namespace App\Filament\Resources\Criteria\Pages;

use App\Filament\Resources\Criteria\CriterionResource;
use App\Models\Criterion;
use App\Services\CriterionWeights;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageCriteria extends ManageRecords
{
    protected static string $resource = CriterionResource::class;

    protected static ?string $title = 'Kriteria dan Bobot';

    public function getSubheading(): string|Htmlable|null
    {
        $summary = CriterionWeights::summary();
        $mode = CriterionWeights::mode() === CriterionWeights::MODE_DIRECT
            ? 'Bobot sedang diatur langsung.'
            : 'Bobot dihitung dari tingkat kepentingan.';

        $color = $summary['ready'] ? 'text-success-600' : 'text-danger-600';

        return new HtmlString(sprintf(
            'Total bobot <strong>%s</strong> (%s%%). <span class="%s">%s</span> %s',
            e(CriterionWeights::formatPoints($summary['total'])),
            e(rtrim(rtrim(number_format($summary['total'] / 100, 2, ',', '.'), '0'), ',')),
            $color,
            e($summary['message']),
            e($mode),
        ));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setWeightsDirectly')
                ->label('Atur bobot langsung')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()->can('create', Criterion::class))
                ->modalHeading('Atur bobot langsung')
                ->modalDescription('Isi persentase bobot setiap kriteria aktif. Totalnya harus tepat 100%. Selama bobot diatur langsung, mengubah tingkat kepentingan tidak menghitung ulang bobot.')
                ->modalSubmitActionLabel('Simpan bobot')
                ->fillForm(function (): array {
                    $data = [];

                    foreach (Criterion::query()->active()->ordered()->get() as $criterion) {
                        $data['w_'.$criterion->id] = $criterion->weightPercent();
                    }

                    return $data;
                })
                ->schema(function (): array {
                    $fields = [];

                    foreach (Criterion::query()->active()->ordered()->get() as $criterion) {
                        $fields[] = TextInput::make('w_'.$criterion->id)
                            ->label($criterion->code.' '.$criterion->name)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->live(onBlur: true);
                    }

                    $fields[] = TextEntry::make('total')
                        ->label('Total')
                        ->state(function (Get $get): string {
                            $total = static::sumPercent(static::collectPercents($get));

                            return rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',').'%'
                                .(abs($total - 100) < 0.005 ? '' : ' (harus 100%)');
                        });

                    return $fields;
                })
                ->action(function (array $data, Action $action): void {
                    $percents = [];

                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, 'w_')) {
                            $percents[(int) substr($key, 2)] = (float) $value;
                        }
                    }

                    if (abs(static::sumPercent($percents) - 100) >= 0.005) {
                        Notification::make()
                            ->title('Total bobot belum 100%')
                            ->body('Sekarang totalnya '.rtrim(rtrim(number_format(static::sumPercent($percents), 2, ',', '.'), '0'), ',').'%. Sesuaikan lalu simpan lagi.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }

                    CriterionWeights::setDirect($percents);

                    Notification::make()->title('Bobot disimpan')->success()->send();
                }),
            Action::make('useImportanceWeights')
                ->label('Hitung dari tingkat kepentingan')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Hitung bobot dari tingkat kepentingan')
                ->modalDescription('Bobot yang diatur langsung akan diganti dengan bobot hasil perhitungan dari tingkat kepentingan setiap kriteria.')
                ->visible(fn (): bool => auth()->user()->can('create', Criterion::class) && CriterionWeights::mode() === CriterionWeights::MODE_DIRECT)
                ->action(function (): void {
                    CriterionWeights::useImportance();

                    Notification::make()->title('Bobot dihitung dari tingkat kepentingan')->success()->send();
                }),
            CreateAction::make()->label('Tambah kriteria'),
        ];
    }

    /**
     * @return array<int, float>
     */
    protected static function collectPercents(Get $get): array
    {
        $percents = [];

        foreach (Criterion::query()->active()->pluck('id') as $id) {
            $percents[$id] = (float) $get('w_'.$id);
        }

        return $percents;
    }

    /**
     * Sums percentages in hundredths of a percent so the comparison with 100 never drifts.
     *
     * @param  array<int, float>  $percents
     */
    protected static function sumPercent(array $percents): float
    {
        return array_sum(array_map(fn (float $percent): int => (int) round($percent * 100), $percents)) / 100;
    }
}
