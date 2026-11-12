<?php

namespace App\Filament\Pages;

use App\Services\Settings\SystemSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * The system settings of PRD F15. The admin sees every section. The leader sees only the rules for decisions
 * (the number of priority orders, approval, protection of old orders). The values are checked and saved by
 * SystemSettings, so the same rules hold whatever way a value arrives.
 *
 * @property-read Schema $form
 */
class SystemSettingsPage extends Page
{
    protected static ?string $slug = 'pengaturan';

    protected static ?string $title = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 30;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return SystemSettings::canOpen(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return SystemSettings::canChangeCompany(auth()->user())
            ? 'Mengatur profil perusahaan, aturan keputusan, perlindungan order lama, dan evaluasi terjadwal.'
            : 'Mengatur aturan keputusan dan perlindungan order lama. Pengaturan lain hanya bisa diubah Admin.';
    }

    #[Unexposed]
    public function mount(): void
    {
        $this->form->fill(SystemSettings::values());
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Profil perusahaan')
                    ->description('Dipakai pada kop laporan PDF dan Excel.')
                    ->visible(fn (): bool => SystemSettings::canChangeCompany(auth()->user()))
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->label('Nama perusahaan')->maxLength(120),
                        TextInput::make('company_phone')->label('Telepon')->maxLength(40),
                        TextInput::make('company_address')->label('Alamat')->maxLength(255)->columnSpanFull(),
                        FileUpload::make('company_logo')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('logos')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(1024)
                            ->helperText('Berkas PNG atau JPG, paling besar 1 MB. Tampil di sebelah kiri nama perusahaan pada kop.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Aturan keputusan')
                    ->description('Berlaku pada perhitungan berikutnya. Evaluasi yang sudah tersimpan tidak berubah.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('top_n_priority')
                            ->label('Jumlah order Prioritas Utama')
                            ->helperText('Jumlah order Antri teratas yang diberi label. Bawaan 5.')
                            ->numeric()->integer()->minValue(1)->maxValue(50)->required(),
                        Toggle::make('approval_required')
                            ->label('Persetujuan Pimpinan wajib')
                            ->helperText('Bila mati, Manajer menerapkan hasil langsung dari status Draf.')
                            ->inline(false),
                        TextInput::make('stale_value_days')
                            ->label('Nilai manual dianggap kedaluwarsa setelah (hari)')
                            ->helperText('Bawaan 3 hari.')
                            ->numeric()->integer()->minValue(0)->maxValue(30)->required(),
                        Select::make('incomplete_orders')
                            ->label('Order yang belum lengkap')
                            ->options([
                                SystemSettings::INCOMPLETE_WORST => 'Ikut dihitung dengan nilai terburuk dan diberi peringatan',
                                SystemSettings::INCOMPLETE_EXCLUDE => 'Dikecualikan dari perhitungan',
                            ])
                            ->native(false)
                            ->required(),
                    ]),
                Section::make('Perlindungan order lama')
                    ->description('Supaya order lama tidak kalah terus oleh pesanan baru.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('lock_in_progress')
                            ->label('Kunci otomatis untuk order Dikerjakan')
                            ->inline(false),
                        TextInput::make('max_wait_days')
                            ->label('Batas tunggu maksimum (hari)')
                            ->helperText('Order Antri yang menunggu lebih lama dieskalasi. Bawaan 7.')
                            ->numeric()->integer()->minValue(1)->maxValue(60)->required(),
                        TextInput::make('held_after_evaluations')
                            ->label('Order Tertahan setelah evaluasi berturut-turut')
                            ->helperText('Jumlah evaluasi berturut-turut di luar daftar prioritas. Bawaan 5.')
                            ->numeric()->integer()->minValue(1)->maxValue(20)->required(),
                        TextInput::make('held_min_days')
                            ->label('Order Tertahan setelah menunggu minimal (hari)')
                            ->helperText('Sengaja lebih pendek dari batas tunggu maksimum. Bawaan 4.')
                            ->numeric()->integer()->minValue(0)->maxValue(59)->required(),
                    ]),
                Section::make('Evaluasi terjadwal')
                    ->description('Perhitungan otomatis setiap hari, hasilnya berstatus Draf dan tetap mengikuti persetujuan.')
                    ->visible(fn (): bool => SystemSettings::canChangeCompany(auth()->user()))
                    ->columns(2)
                    ->schema([
                        Toggle::make('schedule_enabled')
                            ->label('Evaluasi terjadwal aktif')
                            ->inline(false),
                        TextInput::make('schedule_time')
                            ->label('Jam pelaksanaan')
                            ->type('time')
                            ->helperText('Bawaan 06:00. Berjalan bila Cron Job server aktif.')
                            ->required(),
                    ]),
                Section::make('Tampilan')
                    ->visible(fn (): bool => SystemSettings::canChangeCompany(auth()->user()))
                    ->schema([
                        TextInput::make('near_deadline_days')
                            ->label('Batas "mendekati tenggat" (hari)')
                            ->helperText('Dipakai pada filter dan kartu Mendekati tenggat. Bawaan 3.')
                            ->numeric()->integer()->minValue(1)->maxValue(14)->required(),
                    ]),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            Actions::make([
                Action::make('save')
                    ->label('Simpan pengaturan')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn () => $this->save()),
            ]),
        ]);
    }

    public function save(): void
    {
        $user = auth()->user();

        abort_unless(SystemSettings::canOpen($user), 403);

        $state = $this->form->getState();
        $keys = $user->isAdmin() ? array_merge(SystemSettings::DECISION_KEYS, SystemSettings::ADMIN_KEYS) : SystemSettings::DECISION_KEYS;
        $input = array_intersect_key($state, array_flip($keys));

        if (isset($input['company_logo']) && is_array($input['company_logo'])) {
            $input['company_logo'] = array_values($input['company_logo'])[0] ?? null;
        }

        try {
            $changes = SystemSettings::save($input, $user);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ['data.'.$key => $messages])
                ->all());
        }

        if ($changes === []) {
            Notification::make()->title('Tidak ada perubahan untuk disimpan')->send();

            return;
        }

        Notification::make()
            ->title('Pengaturan disimpan')
            ->body(count($changes).' pengaturan diubah dan dicatat di Log Aktivitas.')
            ->success()
            ->send();
    }
}
