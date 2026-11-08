<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\CreateProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\EditProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\Pages\ViewProductionOrder;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\ProductionOrders\RelationManagers\ActivitiesRelationManager;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function validOrderData(array $overrides = []): array
{
    return array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'product_id' => Product::factory()->create(['unit_price' => 150000])->id,
        'quantity' => 100,
        'order_date' => today()->toDateString(),
        'deadline_date' => today()->addDays(14)->toDateString(),
        'unit_price' => 150000,
        'status' => OrderStatus::Queued->value,
    ], $overrides);
}

it('lets every role open the order list, but only the manager add orders', function (): void {
    ProductionOrder::factory()->create();

    foreach ([User::factory()->admin(), User::factory()->manager(), User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create())->get('/admin/production-orders')->assertOk();
    }

    $this->actingAs(User::factory()->manager()->create())->get('/admin/production-orders/create')->assertOk();

    foreach ([User::factory()->admin(), User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create())->get('/admin/production-orders/create')->assertForbidden();
    }
});

it('creates an order with an automatic number and the creator recorded', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    Livewire::test(CreateProductionOrder::class)
        ->fillForm(validOrderData(['notes' => 'Kirim bertahap']))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = ProductionOrder::query()->firstOrFail();

    expect($order->number)->toBe('ORD-'.today()->format('Y').'-0001')
        ->and($order->created_by)->toBe($manager->id)
        ->and($order->status)->toBe(OrderStatus::Queued)
        ->and($order->notes)->toBe('Kirim bertahap');
});

it('fills the unit price from the chosen product and lets the user change it', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $product = Product::factory()->create(['unit_price' => 275000]);

    Livewire::test(CreateProductionOrder::class)
        ->set('data.product_id', $product->id)
        ->assertSet('data.unit_price', '275000.00')
        ->fillForm(validOrderData(['product_id' => $product->id, 'unit_price' => 260000]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProductionOrder::query()->firstOrFail()->unit_price)->toEqual('260000.00');
});

it('refuses a deadline before the entry date', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(CreateProductionOrder::class)
        ->fillForm(validOrderData([
            'order_date' => today()->toDateString(),
            'deadline_date' => today()->subDay()->toDateString(),
        ]))
        ->call('create')
        ->assertHasFormErrors(['deadline_date']);

    expect(ProductionOrder::count())->toBe(0);
});

it('accepts a deadline on the same day as the entry date', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(CreateProductionOrder::class)
        ->fillForm(validOrderData(['deadline_date' => today()->toDateString()]))
        ->call('create')
        ->assertHasNoFormErrors();
});

it('requires a customer, a product and a quantity above zero', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(CreateProductionOrder::class)
        ->fillForm(['customer_id' => null, 'product_id' => null, 'quantity' => 0])
        ->call('create')
        ->assertHasFormErrors(['customer_id' => 'required', 'product_id' => 'required', 'quantity']);
});

it('rejects a duplicate order number', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    ProductionOrder::factory()->create(['number' => 'PO-DUPLIKAT']);

    Livewire::test(CreateProductionOrder::class)
        ->fillForm(validOrderData(['number' => 'PO-DUPLIKAT']))
        ->call('create')
        ->assertHasFormErrors(['number']);
});

it('hides inactive customers and products from new orders but keeps them on old orders', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $activeCustomer = Customer::factory()->create(['name' => 'Pelanggan Aktif']);
    $inactiveCustomer = Customer::factory()->inactive()->create(['name' => 'Pelanggan Lama']);
    $inactiveProduct = Product::factory()->inactive()->create(['name' => 'Produk Lama']);

    Livewire::test(CreateProductionOrder::class)
        ->assertSee('Pelanggan Aktif')
        ->assertDontSee('Pelanggan Lama')
        ->assertDontSee('Produk Lama');

    $oldOrder = ProductionOrder::factory()->for($inactiveCustomer)->for($inactiveProduct)->create();

    Livewire::test(EditProductionOrder::class, ['record' => $oldOrder->getRouteKey()])
        ->assertSee('Pelanggan Lama (nonaktif)')
        ->assertSee('Produk Lama (nonaktif)');
});

it('lets the manager edit an order and writes the change to the history', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);
    $order = ProductionOrder::factory()->create(['quantity' => 100]);

    Livewire::test(EditProductionOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['quantity' => 250])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->fresh()->quantity)->toBe(250)
        ->and($order->fresh()->updated_by)->toBe($manager->id)
        ->and($order->activities()->first()->changeLines())->toBe(['Jumlah: 100 menjadi 250']);
});

it('keeps the supervisor from editing an order but lets them change the status', function (): void {
    $order = ProductionOrder::factory()->create();

    $this->actingAs(User::factory()->supervisor()->create())
        ->get("/admin/production-orders/{$order->getRouteKey()}/edit")
        ->assertForbidden();

    Livewire::test(ListProductionOrders::class)
        ->assertActionHidden(TestAction::make('edit')->table($order))
        ->callAction(TestAction::make('changeStatus')->table($order), ['status' => OrderStatus::InProgress->value]);

    expect($order->fresh()->status)->toBe(OrderStatus::InProgress);
});

it('keeps admin and leader read only on orders', function (): void {
    $order = ProductionOrder::factory()->create();

    foreach ([User::factory()->admin(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ListProductionOrders::class)
            ->assertActionHidden(TestAction::make('edit')->table($order))
            ->assertActionHidden(TestAction::make('changeStatus')->table($order));
    }
});

it('has no way to delete an order', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create();

    Livewire::test(ListProductionOrders::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($order));

    expect(ProductionOrderResource::canDelete($order))->toBeFalse();
});

it('filters the list by late, near deadline, locked and status', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $late = ProductionOrder::factory()->late(2)->create();
    $near = ProductionOrder::factory()->dueInDays(2)->create();
    $far = ProductionOrder::factory()->dueInDays(20)->create();
    $locked = ProductionOrder::factory()->inProgress()->dueInDays(20)->create();

    Livewire::test(ListProductionOrders::class)
        ->assertCanSeeTableRecords([$late, $near, $far, $locked])
        ->filterTable('late', true)
        ->assertCanSeeTableRecords([$late])
        ->assertCanNotSeeTableRecords([$near, $far, $locked])
        ->removeTableFilter('late')
        ->filterTable('near_deadline', true)
        ->assertCanSeeTableRecords([$near])
        ->assertCanNotSeeTableRecords([$late, $far, $locked])
        ->removeTableFilter('near_deadline')
        ->filterTable('locked', true)
        ->assertCanSeeTableRecords([$locked])
        ->assertCanNotSeeTableRecords([$late, $near, $far])
        ->removeTableFilter('locked')
        ->filterTable('status', [OrderStatus::InProgress->value])
        ->assertCanSeeTableRecords([$locked])
        ->assertCanNotSeeTableRecords([$late, $near, $far]);
});

it('searches orders by number and by customer name', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $target = ProductionOrder::factory()->for(Customer::factory()->create(['name' => 'PT Cari Aku']))->create();
    $other = ProductionOrder::factory()->create();

    Livewire::test(ListProductionOrders::class)
        ->searchTable('Cari Aku')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable($other->number)
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$target]);
});

it('shows the days left text and a lock icon for locked orders', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $late = ProductionOrder::factory()->late(4)->create();
    $today = ProductionOrder::factory()->dueInDays(0)->create();
    $soon = ProductionOrder::factory()->dueInDays(12)->create();
    $done = ProductionOrder::factory()->late(4)->status(OrderStatus::Done)->create();
    $locked = ProductionOrder::factory()->inProgress()->create();

    Livewire::test(ListProductionOrders::class)
        ->assertTableColumnStateSet('days_left', 'Terlambat 4 hari', $late)
        ->assertTableColumnStateSet('days_left', 'Hari ini', $today)
        ->assertTableColumnStateSet('days_left', '12 hari lagi', $soon)
        ->assertTableColumnStateSet('days_left', '-', $done)
        ->assertTableColumnStateSet('waiting', '-', $locked);

    expect(ProductionOrderResource::daysLeftColor($late))->toBe('danger')
        ->and(ProductionOrderResource::daysLeftColor($today))->toBe('warning')
        ->and(ProductionOrderResource::daysLeftColor($soon))->toBe('gray')
        ->and(ProductionOrderResource::daysLeftColor($done))->toBe('gray');
});

it('shows the full detail page with the change history', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $order = ProductionOrder::factory()->create(['notes' => 'Packing kardus ganda']);
    $order->update(['status' => OrderStatus::InProgress]);

    Livewire::test(ViewProductionOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee($order->number)
        ->assertSee($order->customer->name)
        ->assertSee($order->product->name)
        ->assertSee('Packing kardus ganda')
        ->assertSee('Dikerjakan');

    expect($order->activities)->toHaveCount(2);

    Livewire::test(ActivitiesRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ViewProductionOrder::class,
    ])
        ->assertCanSeeTableRecords($order->activities)
        ->assertSee('Order diubah')
        ->assertSee('Status: Antri menjadi Dikerjakan');
});

it('opens the detail page for every role', function (): void {
    $order = ProductionOrder::factory()->create();

    foreach ([User::factory()->admin(), User::factory()->manager(), User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create())
            ->get("/admin/production-orders/{$order->getRouteKey()}")
            ->assertOk();
    }
});
