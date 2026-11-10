<?php

namespace App\Services;

use App\Enums\LockType;
use App\Models\ActivityLog;
use App\Models\ProductionOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Puts a manual lock on an open order and takes it off again (PRD F18). A reason is required both ways
 * and each action leaves exactly one row in the activity log. The automatic lock is not handled here,
 * it follows the order status.
 */
class OrderLocker
{
    public function lock(ProductionOrder $order, User $by, ?string $reason, ?CarbonInterface $until = null): void
    {
        Gate::forUser($by)->authorize('lock', $order);

        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan penguncian wajib diisi.']);
        }

        if (mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan paling banyak 255 huruf.']);
        }

        if (! $order->status->isOpen()) {
            throw ValidationException::withMessages(['reason' => 'Order yang sudah selesai atau dibatalkan tidak perlu dikunci.']);
        }

        if ($until !== null && Carbon::instance($until)->startOfDay()->lt(today())) {
            throw ValidationException::withMessages(['until' => 'Tanggal berakhir tidak boleh sebelum hari ini.']);
        }

        if ($order->lockIsActive(today())) {
            throw ValidationException::withMessages(['reason' => $order->lock_type === LockType::Auto
                ? 'Order ini sedang dikerjakan dan sudah terkunci otomatis.'
                : 'Order ini sudah dikunci. Lepas kunci yang lama lebih dulu bila ingin menggantinya.']);
        }

        DB::transaction(function () use ($order, $by, $reason, $until): void {
            $old = $this->snapshot($order);

            $order->forceFill([
                'lock_type' => LockType::Manual,
                'lock_reason' => $reason,
                'locked_by' => $by->id,
                'locked_at' => now(),
                'lock_until' => $until === null ? null : Carbon::instance($until)->startOfDay(),
                'updated_by' => $by->id,
            ])->saveQuietly();

            ActivityLog::record('order.locked', $order, $old, $this->snapshot($order) + ['reason' => $reason], $by);
        });
    }

    public function unlock(ProductionOrder $order, User $by, ?string $reason): void
    {
        Gate::forUser($by)->authorize('lock', $order);

        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan melepas kunci wajib diisi.']);
        }

        if (mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan paling banyak 255 huruf.']);
        }

        if ($order->lock_type !== LockType::Manual) {
            throw ValidationException::withMessages(['reason' => $order->lock_type === LockType::Auto
                ? 'Kunci otomatis lepas sendiri saat order selesai atau dibatalkan, tidak dilepas dengan tangan.'
                : 'Order ini tidak punya kunci manual.']);
        }

        DB::transaction(function () use ($order, $by, $reason): void {
            $old = $this->snapshot($order);

            $order->forceFill([
                'lock_type' => LockType::None,
                'lock_reason' => null,
                'locked_by' => null,
                'locked_at' => null,
                'lock_until' => null,
                'updated_by' => $by->id,
            ])->saveQuietly();

            ActivityLog::record('order.unlocked', $order, $old, $this->snapshot($order) + ['release_reason' => $reason], $by);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProductionOrder $order): array
    {
        return [
            'lock_type' => $order->lock_type->value,
            'lock_reason' => $order->lock_reason,
            'lock_until' => $order->lock_until?->toDateString(),
        ];
    }
}
