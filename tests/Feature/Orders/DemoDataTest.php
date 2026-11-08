<?php

use App\Enums\LockType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoMasterSeeder;
use Database\Seeders\DemoUserSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('seeds four demo accounts, five customers, four products and 25 orders', function (): void {
    expect(User::count())->toBe(4)
        ->and(Customer::count())->toBe(5)
        ->and(Product::count())->toBe(4)
        ->and(ProductionOrder::count())->toBe(25);
});

it('can be seeded twice without duplicating master data', function (): void {
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoMasterSeeder::class);

    expect(User::count())->toBe(4)
        ->and(Customer::count())->toBe(5)
        ->and(Product::count())->toBe(4);
});

it('covers every order situation the next phases need', function (): void {
    expect(ProductionOrder::query()->where('status', OrderStatus::Done)->count())->toBe(3)
        ->and(ProductionOrder::query()->where('status', OrderStatus::Canceled)->count())->toBe(1)
        ->and(ProductionOrder::query()->where('status', OrderStatus::InProgress)->count())->toBe(6)
        ->and(ProductionOrder::query()->late()->count())->toBeGreaterThanOrEqual(3)
        ->and(ProductionOrder::query()->nearDeadline()->count())->toBeGreaterThanOrEqual(2);
});

it('includes queued orders that have waited longer than seven days with far deadlines', function (): void {
    $longWaiting = ProductionOrder::query()
        ->where('status', OrderStatus::Queued)
        ->whereDate('order_date', '<=', today()->subDays(8))
        ->whereDate('deadline_date', '>=', today()->addDays(14))
        ->count();

    expect($longWaiting)->toBeGreaterThanOrEqual(3);
});

it('locks orders in progress automatically and has one manual lock with a reason', function (): void {
    $inProgress = ProductionOrder::query()->where('status', OrderStatus::InProgress)->get();

    expect($inProgress->every(fn (ProductionOrder $order): bool => $order->lock_type === LockType::Auto))->toBeTrue();

    $manual = ProductionOrder::query()->where('lock_type', LockType::Manual)->get();

    expect($manual)->toHaveCount(1)
        ->and($manual->first()->lock_reason)->not->toBeEmpty()
        ->and($manual->first()->locker->email)->toBe('pimpinan@spk.test');
});

it('renders every Phase 2 page with data for every role', function (): void {
    $order = ProductionOrder::query()->firstOrFail();

    foreach (User::all() as $user) {
        $this->flushSession();
        $this->actingAs($user);

        foreach (['/admin/production-orders', "/admin/production-orders/{$order->getRouteKey()}", '/admin/customers', '/admin/products'] as $path) {
            $this->get($path)->assertOk();
        }
    }
});

it('shows seeded customers and products in their lists', function (): void {
    $this->actingAs(User::query()->where('email', 'manajer@spk.test')->firstOrFail());

    $this->get('/admin/customers')->assertSee('PT Langkah Mandiri');
    $this->get('/admin/products')->assertSee('Sepatu Pantofel Pria');
    $this->get('/admin/production-orders')->assertSee('ORD-'.today()->format('Y').'-0001');
});
