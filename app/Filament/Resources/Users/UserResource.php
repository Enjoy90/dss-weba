<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->label('Peran')
                    ->options(UserRole::options())
                    ->required()
                    ->native(false)
                    ->rules([
                        fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $reason = $record?->roleChangeBlockedReason(UserRole::from($value));

                            if ($reason !== null) {
                                $fail($reason);
                            }
                        },
                    ]),
                TextInput::make('password')
                    ->label('Kata sandi awal')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->helperText('Minimal 8 karakter. Pengguna dapat menggantinya sendiri dari menu profil.')
                    ->visibleOn('create'),
                Toggle::make('is_active')
                    ->label('Akun aktif')
                    ->default(true)
                    ->rules([
                        fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($value || $record === null) {
                                return;
                            }

                            $reason = $record->deactivationBlockedReason(auth()->user());

                            if ($reason !== null) {
                                $fail($reason);
                            }
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Terakhir masuk')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(UserRole::options()),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Ubah'),
                    Action::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan pengguna')
                        ->modalDescription('Pengguna tidak dapat masuk lagi, tetapi riwayatnya tetap tersimpan.')
                        ->visible(fn (User $record): bool => $record->is_active)
                        ->action(function (User $record): void {
                            $reason = $record->deactivationBlockedReason(auth()->user());

                            if ($reason !== null) {
                                Notification::make()->title($reason)->danger()->send();

                                return;
                            }

                            $record->update(['is_active' => false]);

                            Notification::make()->title('Pengguna dinonaktifkan')->success()->send();
                        }),
                    Action::make('activate')
                        ->label('Aktifkan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (User $record): bool => ! $record->is_active)
                        ->action(function (User $record): void {
                            $record->update(['is_active' => true]);

                            Notification::make()->title('Pengguna diaktifkan')->success()->send();
                        }),
                    Action::make('resetPassword')
                        ->label('Atur ulang kata sandi')
                        ->icon(Heroicon::OutlinedKey)
                        ->schema([
                            TextInput::make('password')
                                ->label('Kata sandi baru')
                                ->password()
                                ->revealable()
                                ->required()
                                ->rule(Password::default())
                                ->same('passwordConfirmation'),
                            TextInput::make('passwordConfirmation')
                                ->label('Ulangi kata sandi baru')
                                ->password()
                                ->revealable()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data): void {
                            $record->update(['password' => $data['password']]);

                            Notification::make()->title('Kata sandi diatur ulang')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
