<?php

use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderLocker;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
});

function lockInvalid(callable $call, string $field): void
{
    try {
        $call();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    throw new RuntimeException('Diharapkan ValidationException pada '.$field);
}

it('locks an order with a reason and leaves exactly one log row', function (): void {
    $order = ProductionOrder::factory()->create();
    $before = ActivityLog::query()->count();

    app(OrderLocker::class)->lock($order, $this->manager, '  Pelanggan penting  ', today()->addDays(5));

    $fresh = $order->fresh();

    expect($fresh->lock_type)->toBe(LockType::Manual)
        ->and($fresh->lock_reason)->toBe('Pelanggan penting')
        ->and($fresh->locked_by)->toBe($this->manager->id)
        ->and($fresh->locked_at)->not->toBeNull()
        ->and($fresh->lock_until->toDateString())->toBe(today()->addDays(5)->toDateString())
        ->and($fresh->isLocked())->toBeTrue()
        ->and(ActivityLog::query()->count())->toBe($before + 1);

    $log = ActivityLog::query()->where('action', 'order.locked')->sole();

    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->subject_id)->toBe($order->id)
        ->and($log->old_values['lock_type'])->toBe('none')
        ->and($log->new_values)->toMatchArray(['lock_type' => 'manual', 'reason' => 'Pelanggan penting']);
});

it('refuses to lock without a reason and writes no log row', function (): void {
    $order = ProductionOrder::factory()->create();
    $before = ActivityLog::query()->count();

    lockInvalid(fn () => app(OrderLocker::class)->lock($order, $this->manager, null), 'reason');
    lockInvalid(fn () => app(OrderLocker::class)->lock($order, $this->manager, '   '), 'reason');
    lockInvalid(fn () => app(OrderLocker::class)->lock($order, $this->manager, str_repeat('a', 256)), 'reason');

    expect($order->fresh()->lock_type)->toBe(LockType::None)
        ->and(ActivityLog::query()->count())->toBe($before);
});

it('lets an end date be today or later but not earlier', function (): void {
    $order = ProductionOrder::factory()->create();

    lockInvalid(fn () => app(OrderLocker::class)->lock($order, $this->manager, 'Alasan', today()->subDay()), 'until');

    app(OrderLocker::class)->lock($order, $this->manager, 'Sampai hari ini', today());

    expect($order->fresh()->lock_until->toDateString())->toBe(today()->toDateString());
});

it('lets a lock stay without an end date', function (): void {
    $order = ProductionOrder::factory()->create();

    app(OrderLocker::class)->lock($order, $this->leader, 'Tanpa batas');

    expect($order->fresh()->lock_until)->toBeNull()->and($order->fresh()->isLocked())->toBeTrue();
});

it('lets only the manager and the leader lock and unlock', function (): void {
    $order = ProductionOrder::factory()->create();

    foreach ([User::factory()->admin()->create(), User::factory()->supervisor()->create()] as $user) {
        expect(fn () => app(OrderLocker::class)->lock($order, $user, 'Alasan'))->toThrow(AuthorizationException::class);
    }

    app(OrderLocker::class)->lock($order, $this->manager, 'Alasan');

    foreach ([User::factory()->admin()->create(), User::factory()->supervisor()->create()] as $user) {
        expect(fn () => app(OrderLocker::class)->unlock($order->fresh(), $user, 'Alasan'))->toThrow(AuthorizationException::class);
    }

    expect($order->fresh()->lock_type)->toBe(LockType::Manual);
});

it('does not lock a finished order, one already locked, or one being worked on', function (): void {
    $done = ProductionOrder::factory()->status(OrderStatus::Done)->create();
    $working = ProductionOrder::factory()->inProgress()->create();
    $locked = ProductionOrder::factory()->create();
    app(OrderLocker::class)->lock($locked, $this->manager, 'Pertama');

    lockInvalid(fn () => app(OrderLocker::class)->lock($done, $this->manager, 'Alasan'), 'reason');
    lockInvalid(fn () => app(OrderLocker::class)->lock($working, $this->manager, 'Alasan'), 'reason');
    lockInvalid(fn () => app(OrderLocker::class)->lock($locked->fresh(), $this->leader, 'Kedua'), 'reason');

    expect($locked->fresh()->lock_reason)->toBe('Pertama');
});

it('can lock again once the earlier lock has lapsed', function (): void {
    $order = ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Lama', 'lock_until' => today()->subDay()]);

    expect($order->isLocked())->toBeFalse();

    app(OrderLocker::class)->lock($order, $this->manager, 'Baru');

    expect($order->fresh()->lock_reason)->toBe('Baru')->and($order->fresh()->isLocked())->toBeTrue();
});

it('releases a manual lock with a reason and leaves exactly one log row', function (): void {
    $order = ProductionOrder::factory()->create();
    app(OrderLocker::class)->lock($order, $this->manager, 'Pasang', today()->addDays(3));
    $before = ActivityLog::query()->count();

    lockInvalid(fn () => app(OrderLocker::class)->unlock($order->fresh(), $this->leader, ' '), 'reason');
    expect($order->fresh()->lock_type)->toBe(LockType::Manual)->and(ActivityLog::query()->count())->toBe($before);

    app(OrderLocker::class)->unlock($order->fresh(), $this->leader, 'Sudah tidak perlu');

    $fresh = $order->fresh();

    expect($fresh->lock_type)->toBe(LockType::None)
        ->and($fresh->lock_reason)->toBeNull()
        ->and($fresh->locked_by)->toBeNull()
        ->and($fresh->lock_until)->toBeNull()
        ->and(ActivityLog::query()->count())->toBe($before + 1);

    $log = ActivityLog::query()->where('action', 'order.unlocked')->sole();

    expect($log->user_id)->toBe($this->leader->id)
        ->and($log->old_values['lock_reason'])->toBe('Pasang')
        ->and($log->new_values)->toMatchArray(['lock_type' => 'none', 'release_reason' => 'Sudah tidak perlu']);
});

it('does not release an automatic lock by hand or an order that has no manual lock', function (): void {
    $working = ProductionOrder::factory()->inProgress()->create();
    $plain = ProductionOrder::factory()->create();

    lockInvalid(fn () => app(OrderLocker::class)->unlock($working, $this->manager, 'Alasan'), 'reason');
    lockInvalid(fn () => app(OrderLocker::class)->unlock($plain, $this->manager, 'Alasan'), 'reason');

    expect($working->fresh()->lock_type)->toBe(LockType::Auto);
});

it('shows the lock in the history of the order', function (): void {
    $order = ProductionOrder::factory()->create();
    app(OrderLocker::class)->lock($order, $this->manager, 'Terlihat di riwayat');

    $entry = $order->activities()->where('action', 'order.locked')->sole();

    expect($entry->actionLabel())->toBe('Order dikunci')
        ->and(implode(' ', $entry->changeLines()))->toContain('Terlihat di riwayat');
});

it('locks an order by itself when its status becomes Dikerjakan', function (): void {
    $order = ProductionOrder::factory()->create();

    expect($order->fresh()->lock_type)->toBe(LockType::None);

    $order->update(['status' => OrderStatus::InProgress]);

    $fresh = $order->fresh();

    expect($fresh->lock_type)->toBe(LockType::Auto)
        ->and($fresh->locked_at)->not->toBeNull()
        ->and($fresh->locked_by)->toBeNull()
        ->and($fresh->isLocked())->toBeTrue();
});

it('locks an order created as Dikerjakan, for example by an import', function (): void {
    $order = ProductionOrder::factory()->status(OrderStatus::InProgress)->create();

    expect($order->fresh()->lock_type)->toBe(LockType::Auto);
});

it('does not lock by itself when the setting is off', function (): void {
    Setting::put('lock_in_progress', false);
    $order = ProductionOrder::factory()->create();

    $order->update(['status' => OrderStatus::InProgress]);

    expect($order->fresh()->lock_type)->toBe(LockType::None);
});

it('releases every lock and the priority label when an order is finished or canceled', function (): void {
    foreach ([OrderStatus::Done, OrderStatus::Canceled] as $status) {
        $auto = ProductionOrder::factory()->inProgress()->create(['priority' => OrderPriority::Top]);
        $manual = ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Penting', 'locked_by' => $this->manager->id, 'locked_at' => now(), 'priority' => OrderPriority::Top]);

        $auto->update(['status' => $status]);
        $manual->update(['status' => $status]);

        foreach ([$auto->fresh(), $manual->fresh()] as $order) {
            expect($order->lock_type)->toBe(LockType::None)
                ->and($order->lock_reason)->toBeNull()
                ->and($order->locked_by)->toBeNull()
                ->and($order->locked_at)->toBeNull()
                ->and($order->priority)->toBe(OrderPriority::Normal)
                ->and($order->isLocked())->toBeFalse();
        }
    }
});

it('releases only the automatic lock when an order goes back to Antri', function (): void {
    $auto = ProductionOrder::factory()->inProgress()->create();
    $manual = ProductionOrder::factory()->create();
    app(OrderLocker::class)->lock($manual, $this->manager, 'Tetap terkunci');

    $auto->update(['status' => OrderStatus::Queued]);
    $manual->update(['status' => OrderStatus::Queued, 'notes' => 'Diubah']);

    expect($auto->fresh()->lock_type)->toBe(LockType::None)
        ->and($manual->fresh()->lock_type)->toBe(LockType::Manual)
        ->and($manual->fresh()->lock_reason)->toBe('Tetap terkunci');
});

it('replaces a manual lock with the automatic one when the order starts being worked on', function (): void {
    $order = ProductionOrder::factory()->create();
    app(OrderLocker::class)->lock($order, $this->manager, 'Dikunci tangan');

    $order->fresh()->update(['status' => OrderStatus::InProgress]);

    expect($order->fresh()->lock_type)->toBe(LockType::Auto)->and($order->fresh()->lock_reason)->toBeNull();
});

it('leaves the lock alone when something other than the status changes', function (): void {
    $order = ProductionOrder::factory()->inProgress()->create();
    $lockedAt = $order->fresh()->locked_at;

    $this->travelTo(now()->addHour());
    $order->fresh()->update(['notes' => 'Catatan baru']);

    expect($order->fresh()->locked_at->toDateTimeString())->toBe($lockedAt->toDateTimeString());
});

it('does not count a lapsed manual lock as locked', function (): void {
    $active = ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Aktif', 'lock_until' => today()]);
    $open = ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Tanpa batas', 'lock_until' => null]);
    $lapsed = ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Lewat', 'lock_until' => today()->subDay()]);
    $auto = ProductionOrder::factory()->inProgress()->create();
    $plain = ProductionOrder::factory()->create();

    expect(ProductionOrder::query()->locked()->pluck('id')->all())->toEqualCanonicalizing([$active->id, $open->id, $auto->id])
        ->and($lapsed->isLocked())->toBeFalse()
        ->and($plain->isLocked())->toBeFalse();
});
