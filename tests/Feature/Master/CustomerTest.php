<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('lets admin and manager create customers and shows the list to everyone', function (): void {
    foreach ([User::factory()->admin(), User::factory()->manager()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ManageCustomers::class)
            ->callAction('create', [
                'code' => 'PLG-'.fake()->unique()->numerify('###'),
                'name' => 'PT Contoh '.fake()->unique()->numerify('###'),
                'urgency_level' => 4,
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();
    }

    expect(Customer::count())->toBe(2);

    foreach ([User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create())
            ->get('/admin/customers')
            ->assertOk();
    }
});

it('keeps supervisor and leader from adding or editing customers', function (): void {
    $customer = Customer::factory()->create();

    foreach ([User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ManageCustomers::class)
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($customer))
            ->assertActionHidden(TestAction::make('deactivate')->table($customer));
    }
});

it('requires a unique code and an urgency between 1 and 5', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    Customer::factory()->create(['code' => 'PLG-001']);

    Livewire::test(ManageCustomers::class)
        ->callAction('create', ['code' => 'PLG-001', 'name' => 'Duplikat', 'urgency_level' => 3])
        ->assertHasFormErrors(['code']);

    Livewire::test(ManageCustomers::class)
        ->callAction('create', ['code' => 'PLG-002', 'name' => 'Urgensi salah', 'urgency_level' => 9])
        ->assertHasFormErrors(['urgency_level']);
});

it('shows the number of active orders per customer', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $customer = Customer::factory()->create();

    ProductionOrder::factory()->count(2)->for($customer)->create();
    ProductionOrder::factory()->for($customer)->inProgress()->create();
    ProductionOrder::factory()->for($customer)->status(OrderStatus::Done)->create();

    Livewire::test(ManageCustomers::class)
        ->assertCanSeeTableRecords([$customer])
        ->assertTableColumnStateSet('active_orders_count', 3, $customer);
});

it('deactivates and reactivates a customer instead of deleting', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $customer = Customer::factory()->create();

    Livewire::test(ManageCustomers::class)
        ->callAction(TestAction::make('deactivate')->table($customer))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($customer));

    expect($customer->fresh()->is_active)->toBeFalse();

    Livewire::test(ManageCustomers::class)
        ->callAction(TestAction::make('activate')->table($customer));

    expect($customer->fresh()->is_active)->toBeTrue();
});

it('filters customers by status', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $active = Customer::factory()->create();
    $inactive = Customer::factory()->inactive()->create();

    Livewire::test(ManageCustomers::class)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$active]);
});
