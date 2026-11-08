<?php

use App\Enums\LockType;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\ProductionOrder;
use App\Models\User;

it('creates sequential unique order numbers per year', function (): void {
    $first = ProductionOrder::factory()->create();
    $second = ProductionOrder::factory()->create();

    $year = today()->format('Y');

    expect($first->number)->toBe("ORD-{$year}-0001")
        ->and($second->number)->toBe("ORD-{$year}-0002");
});

it('keeps a number typed by the user', function (): void {
    $order = ProductionOrder::factory()->create(['number' => 'PO-MANUAL-77']);

    expect($order->number)->toBe('PO-MANUAL-77');
});

it('does not reuse the number of a soft deleted order', function (): void {
    $first = ProductionOrder::factory()->create();
    $first->delete();

    $next = ProductionOrder::factory()->create();

    expect($next->number)->not->toBe($first->number);
});

it('sets the creator from the signed in user', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $order = ProductionOrder::factory()->create(['created_by' => null]);

    expect($order->created_by)->toBe($manager->id);
});

it('counts days left and flags late orders only while the order is open', function (): void {
    $late = ProductionOrder::factory()->late(3)->create();
    $upcoming = ProductionOrder::factory()->dueInDays(5)->create();
    $doneAndOverdue = ProductionOrder::factory()->late(3)->status(OrderStatus::Done)->create();

    expect($late->daysLeft())->toBe(-3)
        ->and($late->isLate())->toBeTrue()
        ->and($upcoming->daysLeft())->toBe(5)
        ->and($upcoming->isLate())->toBeFalse()
        ->and($doneAndOverdue->isLate())->toBeFalse();
});

it('does not treat an order due today as late', function (): void {
    $order = ProductionOrder::factory()->dueInDays(0)->create();

    expect($order->isLate())->toBeFalse()
        ->and($order->daysLeft())->toBe(0);
});

it('selects late and near deadline orders with the right boundaries', function (): void {
    $overdue = ProductionOrder::factory()->late(1)->create();
    $today = ProductionOrder::factory()->dueInDays(0)->create();
    $threeDays = ProductionOrder::factory()->dueInDays(3)->create();
    $fourDays = ProductionOrder::factory()->dueInDays(4)->create();
    $canceledOverdue = ProductionOrder::factory()->late(2)->status(OrderStatus::Canceled)->create();

    expect(ProductionOrder::query()->late()->pluck('id')->all())->toBe([$overdue->id])
        ->and(ProductionOrder::query()->nearDeadline()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$today->id, $threeDays->id])->sort()->values()->all())
        ->and(ProductionOrder::query()->nearDeadline()->pluck('id')->all())->not->toContain($fourDays->id, $canceledOverdue->id);
});

it('counts waiting days from the day the order came in', function (): void {
    $order = ProductionOrder::factory()->enteredDaysAgo(9)->create();

    expect($order->waitingDays())->toBe(9);
});

it('calculates the order value from quantity and unit price', function (): void {
    $order = ProductionOrder::factory()->create(['quantity' => 200, 'unit_price' => 150000]);
    $noPrice = ProductionOrder::factory()->create(['unit_price' => null]);

    expect($order->totalValue())->toBe(30000000.0)
        ->and($noPrice->totalValue())->toBeNull();
});

it('selects locked orders', function (): void {
    $inProgress = ProductionOrder::factory()->inProgress()->create();
    $plain = ProductionOrder::factory()->create();

    expect(ProductionOrder::query()->locked()->pluck('id')->all())->toBe([$inProgress->id])
        ->and($inProgress->isLocked())->toBeTrue()
        ->and($inProgress->lock_type)->toBe(LockType::Auto)
        ->and($plain->isLocked())->toBeFalse();
});

it('writes a history entry when an order is created and when it changes', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $order = ProductionOrder::factory()->create();
    $order->update(['status' => OrderStatus::InProgress]);

    $entries = $order->activities()->reorder('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->action)->toBe('order.created')
        ->and($entries[1]->action)->toBe('order.updated')
        ->and($entries[1]->user_id)->toBe($manager->id)
        ->and($entries[1]->changeLines())->toBe(['Status: Antri menjadi Dikerjakan']);
});

it('does not write a history entry when nothing changed', function (): void {
    $order = ProductionOrder::factory()->create();
    $before = ActivityLog::count();

    $order->update(['quantity' => $order->quantity]);

    expect(ActivityLog::count())->toBe($before);
});
