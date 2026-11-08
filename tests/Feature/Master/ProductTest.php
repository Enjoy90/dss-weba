<?php

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('lets admin and manager create products with optional price and time', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageProducts::class)
        ->callAction('create', ['code' => 'PRD-100', 'name' => 'Sepatu Uji', 'is_active' => true])
        ->assertHasNoFormErrors();

    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ManageProducts::class)
        ->callAction('create', [
            'code' => 'PRD-101',
            'name' => 'Sandal Uji',
            'category' => 'Sandal',
            'unit_price' => 150000,
            'minutes_per_unit' => 30,
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    expect(Product::query()->where('code', 'PRD-100')->value('unit_price'))->toBeNull()
        ->and(Product::query()->where('code', 'PRD-101')->value('unit_price'))->toEqual('150000.00');
});

it('rejects a duplicate product code and a negative price', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    Product::factory()->create(['code' => 'PRD-001']);

    Livewire::test(ManageProducts::class)
        ->callAction('create', ['code' => 'PRD-001', 'name' => 'Kembar'])
        ->assertHasFormErrors(['code']);

    Livewire::test(ManageProducts::class)
        ->callAction('create', ['code' => 'PRD-002', 'name' => 'Harga minus', 'unit_price' => -5])
        ->assertHasFormErrors(['unit_price']);
});

it('shows products to every role but only admin and manager can change them', function (): void {
    $product = Product::factory()->create();

    foreach ([User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ManageProducts::class)
            ->assertCanSeeTableRecords([$product])
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($product));
    }
});

it('deactivates and reactivates a product instead of deleting', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $product = Product::factory()->create();

    Livewire::test(ManageProducts::class)
        ->callAction(TestAction::make('deactivate')->table($product));

    expect($product->fresh()->is_active)->toBeFalse();

    Livewire::test(ManageProducts::class)
        ->callAction(TestAction::make('activate')->table($product));

    expect($product->fresh()->is_active)->toBeTrue();
});
