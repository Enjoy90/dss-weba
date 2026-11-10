<?php

namespace App\Filament\Actions;

use App\Enums\LockType;
use App\Models\ProductionOrder;
use App\Services\OrderLocker;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The two buttons that put on and take off a manual lock, shared by the order list and the order page.
 * The rules live in OrderLocker, so a request that skips the button is stopped there as well.
 */
class OrderLockActions
{
    public static function lock(): Action
    {
        return Action::make('lockOrder')
            ->label('Pasang kunci')
            ->icon(Heroicon::OutlinedLockClosed)
            ->visible(fn (ProductionOrder $record): bool => Gate::allows('lock', $record) && $record->status->isOpen() && ! $record->isLocked())
            ->modalHeading(fn (ProductionOrder $record): string => 'Kunci '.$record->number)
            ->modalDescription('Order terkunci tetap berada di atas order biasa pada perhitungan berikutnya. Kunci dan alasannya tercatat di riwayat order.')
            ->modalSubmitActionLabel('Pasang kunci')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan penguncian')
                    ->required()
                    ->rows(2)
                    ->maxLength(255),
                DatePicker::make('until')
                    ->label('Kunci berakhir (boleh dikosongkan)')
                    ->helperText('Setelah tanggal ini kunci lepas sendiri. Kosong berarti tanpa batas.')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->minDate(today()),
            ])
            ->action(fn (ProductionOrder $record, array $data) => static::run(
                fn () => app(OrderLocker::class)->lock($record, auth()->user(), $data['reason'] ?? null, filled($data['until'] ?? null) ? Carbon::parse($data['until']) : null),
                $record->number.' dikunci.',
            ));
    }

    public static function unlock(): Action
    {
        return Action::make('unlockOrder')
            ->label('Lepas kunci')
            ->icon(Heroicon::OutlinedLockOpen)
            ->visible(fn (ProductionOrder $record): bool => Gate::allows('lock', $record) && $record->lock_type === LockType::Manual)
            ->modalHeading(fn (ProductionOrder $record): string => 'Lepas kunci '.$record->number)
            ->modalDescription(fn (ProductionOrder $record): string => 'Kunci dipasang dengan alasan: '.($record->lock_reason ?? '-').'. Alasan pelepasan juga tercatat di riwayat order.')
            ->modalSubmitActionLabel('Lepas kunci')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan melepas kunci')
                    ->required()
                    ->rows(2)
                    ->maxLength(255),
            ])
            ->action(fn (ProductionOrder $record, array $data) => static::run(
                fn () => app(OrderLocker::class)->unlock($record, auth()->user(), $data['reason'] ?? null),
                'Kunci '.$record->number.' dilepas.',
            ));
    }

    protected static function run(callable $change, string $success): void
    {
        try {
            $change();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Kunci tidak diubah')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        } catch (AuthorizationException) {
            Notification::make()->title('Anda tidak berhak mengubah kunci')->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }
}
