<?php

namespace App\Filament\Pages;

use App\Enums\CriterionDirection;
use App\Models\Criterion;
use App\Services\ScaleRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ScaleSettings extends Page
{
    protected static ?string $slug = 'skala-nilai';

    protected static ?string $title = 'Skala Nilai';

    protected static ?string $navigationLabel = 'Skala Nilai';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Prioritas';

    protected static ?int $navigationSort = 20;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Criterion::class) ?? false;
    }

    #[Unexposed]
    public function mount(): void
    {
        $first = Criterion::query()->ordered()->first();

        $this->form->fill([
            'criterion_id' => $first?->id,
            'scales' => $first ? static::scalesFor($first) : [],
        ]);
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        $canEdit = $this->canEdit();

        return $schema
            ->statePath('data')
            ->components([
                Select::make('criterion_id')
                    ->label('Kriteria')
                    ->options(fn (): array => Criterion::query()->ordered()->get()
                        ->mapWithKeys(fn (Criterion $criterion): array => [$criterion->id => $criterion->code.' '.$criterion->name])
                        ->all())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $criterion = $state ? Criterion::query()->find($state) : null;

                        $set('scales', $criterion ? static::scalesFor($criterion) : []);
                        $set('sample', null);
                    }),
                TextEntry::make('direction_hint')
                    ->label('Arah nilai')
                    ->state(function (Get $get): string {
                        $criterion = static::criterionFrom($get('criterion_id'));

                        if ($criterion === null) {
                            return '-';
                        }

                        $unit = $criterion->unit ? ' ('.$criterion->unit.')' : '';

                        return $criterion->direction === CriterionDirection::LowerFirst
                            ? 'Makin kecil didahulukan'.$unit.'. Skala 5 harus berada di rentang paling kecil.'
                            : 'Makin besar didahulukan'.$unit.'. Skala 5 harus berada di rentang paling besar.';
                    }),
                Repeater::make('scales')
                    ->label('Rentang nilai')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->disabled(! $canEdit)
                    ->columns(4)
                    ->itemLabel(fn (array $state): string => 'Skala '.($state['score'] ?? ''))
                    ->helperText('Batas bawah termasuk dalam rentang, batas atas tidak. Kosongkan batas bawah pada rentang paling kecil dan batas atas pada rentang paling besar.')
                    ->schema([
                        TextInput::make('score')
                            ->label('Skala')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('label')
                            ->label('Keterangan')
                            ->maxLength(80),
                        TextInput::make('lower_bound')
                            ->label('Batas bawah (termasuk)')
                            ->numeric()
                            ->live(onBlur: true),
                        TextInput::make('upper_bound')
                            ->label('Batas atas (tidak termasuk)')
                            ->numeric()
                            ->live(onBlur: true),
                    ]),
                Section::make('Uji rentang')
                    ->description('Ketik sebuah nilai asli untuk melihat skala yang dihasilkan, sebelum rentang disimpan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sample')
                            ->label('Nilai asli contoh')
                            ->numeric()
                            ->live(debounce: 500)
                            ->dehydrated(false),
                        TextEntry::make('sample_result')
                            ->label('Hasil')
                            ->state(function (Get $get): string {
                                $sample = $get('sample');

                                if ($sample === null || $sample === '') {
                                    return 'Isi nilai contoh di sebelah kiri.';
                                }

                                $criterion = static::criterionFrom($get('criterion_id'));

                                if ($criterion === null) {
                                    return '-';
                                }

                                $rows = static::rowsFromState($get('scales') ?? []);
                                $errors = ScaleRules::validate($rows, $criterion->direction);

                                if ($errors !== []) {
                                    return 'Rentang belum benar: '.$errors[0];
                                }

                                $score = ScaleRules::convert($rows, (float) $sample);

                                return $score === null ? 'Tidak ada skala yang cocok.' : 'Nilai '.$sample.' menjadi skala '.$score.'.';
                            }),
                    ]),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    #[Unexposed]
    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())->key('form-actions'),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        if (! $this->canEdit()) {
            return [];
        }

        return [
            Action::make('save')
                ->label('Simpan skala')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    #[Exposed]
    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $state = $this->form->getState();
        $criterion = static::criterionFrom($state['criterion_id'] ?? null);

        if ($criterion === null) {
            return;
        }

        $scales = $state['scales'] ?? [];
        $errors = ScaleRules::validate(static::rowsFromState($scales), $criterion->direction);

        if ($errors !== []) {
            Notification::make()
                ->title('Rentang skala belum benar')
                ->body(implode("\n", $errors))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        foreach ($scales as $row) {
            $criterion->scales()->updateOrCreate(
                ['score' => (int) $row['score']],
                [
                    'label' => filled($row['label'] ?? null) ? $row['label'] : null,
                    'lower_bound' => static::numberOrNull($row['lower_bound'] ?? null),
                    'upper_bound' => static::numberOrNull($row['upper_bound'] ?? null),
                ],
            );
        }

        Notification::make()->title('Skala '.$criterion->code.' disimpan')->success()->send();
    }

    protected function canEdit(): bool
    {
        return auth()->user()?->can('editScale', Criterion::class) ?? false;
    }

    protected static function criterionFrom(mixed $id): ?Criterion
    {
        return $id ? Criterion::query()->find($id) : null;
    }

    /**
     * Five rows from skala 5 down to skala 1, filled from the database or empty when still unset.
     *
     * @return array<int, array{score: int, label: ?string, lower_bound: mixed, upper_bound: mixed}>
     */
    protected static function scalesFor(Criterion $criterion): array
    {
        $saved = $criterion->scales()->get()->keyBy('score');
        $rows = [];

        foreach ([5, 4, 3, 2, 1] as $score) {
            $scale = $saved->get($score);

            $rows[] = [
                'score' => $score,
                'label' => $scale?->label,
                'lower_bound' => $scale?->lower_bound,
                'upper_bound' => $scale?->upper_bound,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $scales
     * @return array<int, array{score: int, lower: ?float, upper: ?float}>
     */
    protected static function rowsFromState(array $scales): array
    {
        $rows = [];

        foreach ($scales as $row) {
            $rows[] = [
                'score' => (int) ($row['score'] ?? 0),
                'lower' => static::numberOrNull($row['lower_bound'] ?? null),
                'upper' => static::numberOrNull($row['upper_bound'] ?? null),
            ];
        }

        return $rows;
    }

    protected static function numberOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
