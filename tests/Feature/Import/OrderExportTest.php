<?php

use App\Enums\ImportMode;
use App\Enums\OrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use App\Services\Export\OrderSheetWriter;
use App\Services\Import\OrderImporter;
use App\Services\Import\OrderSheetReader;
use Database\Seeders\CriteriaSeeder;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

/**
 * Reads back the file a Livewire download action produced, as rows with the title row first.
 *
 * @return array<int, array<int, mixed>>
 */
function downloadedRows(Testable $page): array
{
    $content = base64_decode($page->effects['download']['content']);
    $path = tempnam(sys_get_temp_dir(), 'dl');
    file_put_contents($path, $content);

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        break;
    }

    $reader->close();
    @unlink($path);

    return $rows;
}

it('offers import and export to admin and the manager only', function (): void {
    foreach ([User::factory()->admin(), User::factory()->manager()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ListProductionOrders::class)
            ->assertActionVisible('export')
            ->assertActionVisible('import');
    }

    foreach ([User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ListProductionOrders::class)
            ->assertActionHidden('export')
            ->assertActionHidden('import');
    }
});

it('exports the list as an Excel file with the template columns first', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    ProductionOrder::factory()->count(3)->create();

    $page = Livewire::test(ListProductionOrders::class)->callAction('export');

    $page->assertFileDownloaded();

    $rows = downloadedRows($page);

    expect($rows[0])->toBe([...OrderSheetWriter::TEMPLATE_COLUMNS, ...OrderSheetWriter::EXTRA_COLUMNS])
        ->and($rows)->toHaveCount(4);
});

it('exports only what the filters leave on screen', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $late = ProductionOrder::factory()->late(2)->create(['number' => 'TERLAMBAT-1']);
    ProductionOrder::factory()->dueInDays(20)->create(['number' => 'LONGGAR-1']);
    ProductionOrder::factory()->status(OrderStatus::Done)->create(['number' => 'SELESAI-1']);

    $page = Livewire::test(ListProductionOrders::class)
        ->filterTable('late', true)
        ->callAction('export');

    $rows = downloadedRows($page);

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe('TERLAMBAT-1');
});

it('exports what the search left on screen and keeps the sort order', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    ProductionOrder::factory()->for(Customer::factory()->create(['name' => 'PT Cari Aku']))->create(['number' => 'CARI-1']);
    ProductionOrder::factory()->create(['number' => 'LAIN-1']);

    $page = Livewire::test(ListProductionOrders::class)->searchTable('Cari Aku')->callAction('export');

    $rows = downloadedRows($page);

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe('CARI-1');
});

it('writes the values of an order the way the importer expects them', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);
    $this->seed(CriteriaSeeder::class);

    $order = ProductionOrder::factory()->for(Customer::factory()->create(['code' => 'PLG-9', 'name' => 'PT Sembilan']))->create([
        'number' => 'EKS-1', 'quantity' => 120, 'unit_price' => 130000, 'status' => OrderStatus::InProgress,
    ]);

    $writer = app(AssessmentWriter::class);
    $writer->setManual($order, Criterion::query()->where('code', 'C3')->first(), 75, $manager);
    $writer->setManual($order, Criterion::query()->where('code', 'C4')->first(), 18.5, $manager);

    $page = Livewire::test(ListProductionOrders::class)->callAction('export');
    $row = downloadedRows($page)[1];

    expect($row[0])->toBe('EKS-1')
        ->and($row[1])->toBe('PLG-9')
        ->and($row[2])->toBe($order->product->code)
        ->and($row[3])->toEqual(120)
        ->and($row[4])->toBeInstanceOf(DateTimeInterface::class)
        ->and($row[6])->toEqual(130000)
        ->and($row[7])->toBe('Dikerjakan')
        ->and($row[8])->toEqual(75)
        ->and($row[9])->toEqual(18.5)
        ->and($row[10])->toBeEmpty()
        ->and($row[11])->toBe('PT Sembilan');
});

it('can be exported and imported again without changing anything', function (): void {
    $this->seed(DatabaseSeeder::class);
    $manager = User::query()->where('email', 'manajer@spk.test')->firstOrFail();
    $this->actingAs($manager);

    $snapshot = fn (): array => ProductionOrder::query()->orderBy('id')->get()->map(fn (ProductionOrder $order): array => [
        $order->number, $order->customer_id, $order->product_id, $order->quantity,
        $order->order_date->toDateString(), $order->deadline_date->toDateString(),
        (string) $order->unit_price, $order->status->value,
    ])->all();

    $values = fn (): array => Assessment::query()->orderBy('id')->get()->map(fn (Assessment $a): array => [$a->production_order_id, $a->criterion_id, (string) $a->raw_value, $a->is_overridden])->all();

    $before = $snapshot();
    $valuesBefore = $values();

    $page = Livewire::test(ListProductionOrders::class)->callAction('export');
    $path = tempnam(sys_get_temp_dir(), 'rt');
    file_put_contents($path, base64_decode($page->effects['download']['content']));

    $read = (new OrderSheetReader)->read($path);
    $report = app(OrderImporter::class)->analyze($read['rows'], ImportMode::AddOrUpdate);

    expect($read['errors'])->toBe([])
        ->and($report->rejected())->toBe(0)
        ->and($report->updated())->toBe(ProductionOrder::count());

    app(OrderImporter::class)->commit($report, $manager);

    expect($snapshot())->toBe($before)
        ->and($values())->toBe($valuesBefore);

    @unlink($path);
});

it('keeps price columns in the export for admin and the manager who may see them', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    ProductionOrder::factory()->create(['quantity' => 10, 'unit_price' => 50000]);

    $rows = downloadedRows(Livewire::test(ListProductionOrders::class)->callAction('export'));

    expect($rows[0])->toContain('Harga satuan (Rp)')->toContain('Nilai order (Rp)')
        ->and($rows[1][6])->toEqual(50000)
        ->and($rows[1][14])->toEqual(500000);
});
