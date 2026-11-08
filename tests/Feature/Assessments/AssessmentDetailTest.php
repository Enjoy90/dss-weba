<?php

use App\Filament\Resources\ProductionOrders\Pages\ViewProductionOrder;
use App\Filament\Resources\ProductionOrders\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\ProductionOrders\RelationManagers\AssessmentHistoriesRelationManager;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
});

it('shows the assessment of the order on its detail page', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $order = ProductionOrder::factory()
        ->for(Customer::factory()->urgency(4))
        ->enteredDaysAgo(10)
        ->create(['quantity' => 200, 'unit_price' => 150000]);

    app(AssessmentWriter::class)->setManual($order, $this->criteria['C3'], 75, auth()->user());

    Livewire::test(ViewProductionOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Penilaian')
        ->assertSee('Belum lengkap')
        ->assertSee('C1 Sisa waktu menuju tenggat')
        ->assertSee('10 hari')
        ->assertSee('75%')
        ->assertSee('Rp 30.000.000')
        ->assertSee('Belum diisi');
});

it('shows a complete order as complete and a stale one as needing an update', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $writer = app(AssessmentWriter::class);

    $order = ProductionOrder::factory()->create(['unit_price' => 100000]);

    foreach (['C3', 'C4', 'C5'] as $code) {
        $writer->setManual($order, $this->criteria[$code], 50, auth()->user());
    }

    Livewire::test(ViewProductionOrder::class, ['record' => $order->getRouteKey()])->assertSee('Lengkap');

    Assessment::query()->where('production_order_id', $order->id)->update(['value_updated_at' => now()->subDays(6)]);

    Livewire::test(ViewProductionOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Perlu diperbarui')
        ->assertSee('Manual, perlu diperbarui');
});

it('hides the order value and the unit price from the supervisor on the detail page', function (): void {
    $order = ProductionOrder::factory()->create(['quantity' => 200, 'unit_price' => 150000]);

    $this->actingAs(User::factory()->supervisor()->create());

    Livewire::test(ViewProductionOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('C1 Sisa waktu menuju tenggat')
        ->assertDontSee('C6 Nilai order')
        ->assertDontSee('Harga satuan')
        ->assertDontSee('Rp 30.000.000')
        ->assertDontSee('Rp 150.000');

    foreach ([User::factory()->manager(), User::factory()->admin(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ViewProductionOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('C6 Nilai order')
            ->assertSee('Harga satuan')
            ->assertSee('Rp 30.000.000');
    }
});

it('hides the unit price column of products from the supervisor only', function (): void {
    Product::factory()->create(['unit_price' => 250000]);

    $this->actingAs(User::factory()->supervisor()->create());

    Livewire::test(ManageProducts::class)->assertTableColumnHidden('unit_price');

    foreach ([User::factory()->manager(), User::factory()->admin(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ManageProducts::class)->assertTableColumnVisible('unit_price');
    }
});

it('leaves price changes out of the order history for the supervisor', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $order = ProductionOrder::factory()->create(['unit_price' => 100000]);
    $order->update(['unit_price' => 120000, 'quantity' => 300]);

    $log = $order->activities()->where('action', 'order.updated')->firstOrFail();

    expect($log->changeLines())->toHaveCount(2)
        ->and($log->changeLines(true))->toBe(['Jumlah: '.$order->activities()->where('action', 'order.created')->firstOrFail()->new_values['quantity'].' menjadi 300']);

    $this->actingAs(User::factory()->supervisor()->create());

    Livewire::test(ActivitiesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewProductionOrder::class])
        ->assertSee('Jumlah')
        ->assertDontSee('Harga satuan');
});

it('lists the value history of the order with old and new values', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $order = ProductionOrder::factory()->create();
    $writer = app(AssessmentWriter::class);

    $writer->setManual($order, $this->criteria['C3'], 60, $manager);
    $writer->setManual($order, $this->criteria['C3'], 85, $manager);
    $writer->override($order, $this->criteria['C2'], 5, 'Pelanggan strategis', $manager);

    expect($order->assessmentHistories()->count())->toBe(3);

    Livewire::test(AssessmentHistoriesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewProductionOrder::class])
        ->assertCanSeeTableRecords($order->assessmentHistories)
        ->assertSee('C3 Ketersediaan bahan baku')
        ->assertSee('60%')
        ->assertSee('85%')
        ->assertSee('kosong')
        ->assertSee($manager->name);
});

it('keeps the value history of the order value away from the supervisor', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $order = ProductionOrder::factory()->create(['unit_price' => null]);
    $writer = app(AssessmentWriter::class);

    $writer->setManual($order, $this->criteria['C6'], 90000000, $manager);
    $writer->setManual($order, $this->criteria['C3'], 70, $manager);

    $this->actingAs(User::factory()->supervisor()->create());

    Livewire::test(AssessmentHistoriesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewProductionOrder::class])
        ->assertSee('C3 Ketersediaan bahan baku')
        ->assertDontSee('C6 Nilai order')
        ->assertDontSee('Rp 90.000.000');

    $this->actingAs($manager);

    Livewire::test(AssessmentHistoriesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewProductionOrder::class])
        ->assertSee('C6 Nilai order')
        ->assertSee('Rp 90.000.000');
});
