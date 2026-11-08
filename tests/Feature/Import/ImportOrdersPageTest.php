<?php

use App\Enums\ImportMode;
use App\Filament\Pages\ImportOrders;
use App\Models\Assessment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Export\OrderSheetWriter;
use App\Services\Import\OrderImporter;
use App\Services\Import\OrderSheetReader;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);

    Customer::factory()->create(['code' => 'PLG-001']);
    Product::factory()->create(['code' => 'PRD-001', 'unit_price' => 150000]);
});

function xlsxUpload(array $rows, string $name = 'order.xlsx'): UploadedFile
{
    $path = makeXlsx([templateHeader(), ...$rows]);

    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
}

it('opens for admin and the manager only', function (): void {
    foreach ([User::factory()->admin(), User::factory()->manager()] as $factory) {
        $this->flushSession();
        $this->actingAs($factory->create())->get('/admin/impor-order')->assertOk();
    }

    foreach ([User::factory()->supervisor(), User::factory()->leader()] as $factory) {
        $this->flushSession();
        $this->actingAs($factory->create())->get('/admin/impor-order')->assertForbidden();
    }
});

it('checks an uploaded file and shows what would happen without saving anything', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $rows = [templateRow(['number' => 'A-1']), templateRow(['number' => 'A-2']), templateRow(['number' => 'A-3', 'customer' => 'XXX'])];

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload($rows), 'mode' => ImportMode::AddOnly->value])
        ->call('check');

    expect($page->get('preview')['created'])->toBe(2)
        ->and($page->get('preview')['rejected'])->toBe(1)
        ->and($page->get('preview')['can_import'])->toBeTrue()
        ->and($page->get('preview')['issues'][0]['row'])->toBe('4')
        ->and($page->get('preview')['issues'][0]['reason'])->toContain('XXX');

    $page->assertSee('Hasil pemeriksaan')->assertSee('Kode pelanggan');

    expect(ProductionOrder::count())->toBe(0);
});

it('imports the good rows after the check and shows the summary', function (): void {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $rows = [
        templateRow(['number' => 'A-1', 'material' => 60, 'hours' => 20, 'progress' => 5]),
        templateRow(['number' => 'A-2']),
        templateRow(['number' => 'A-3', 'quantity' => 0]),
    ];

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload($rows), 'mode' => ImportMode::AddOnly->value])
        ->call('check')
        ->call('import');

    expect($page->get('result'))->toBe(['created' => 2, 'updated' => 0, 'skipped' => 0, 'rejected' => 1])
        ->and($page->get('preview'))->toBe([])
        ->and(ProductionOrder::query()->pluck('number')->sort()->values()->all())->toBe(['A-1', 'A-2'])
        ->and(Assessment::count())->toBe(3)
        ->and(ProductionOrder::query()->where('number', 'A-1')->value('created_by'))->toBe($manager->id);

    expect($page->get('leftOut'))->toHaveCount(1)
        ->and($page->get('leftOut')[0]['row'])->toBe('4')
        ->and($page->get('leftOut')[0]['kind'])->toBe('Ditolak')
        ->and($page->get('leftOut')[0]['reason'])->toContain('Jumlah');

    $page->assertSee('Hasil impor')->assertSee('Baris yang tidak diimpor');
});

it('lists no more than ten sample rows but says how many more will be imported', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload(array_fill(0, 25, templateRow()))])
        ->call('check');

    expect($page->get('preview')['sample'])->toHaveCount(10)
        ->and($page->get('preview')['more'])->toBe(15)
        ->and($page->get('preview')['created'])->toBe(25);
});

it('clears the left out rows when a new file is checked', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload([templateRow(), templateRow(['quantity' => 0])])])
        ->call('check')
        ->call('import');

    expect($page->get('leftOut'))->toHaveCount(1);

    $page->fillForm(['file' => xlsxUpload([templateRow()])])->call('check');

    expect($page->get('leftOut'))->toBe([]);
});

it('imports in update mode and reports the updated orders', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    ProductionOrder::factory()->create(['number' => 'ADA-1', 'quantity' => 10]);

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload([templateRow(['number' => 'ADA-1', 'quantity' => 500]), templateRow(['number' => 'BARU-1'])]), 'mode' => ImportMode::AddOrUpdate->value])
        ->call('check');

    expect($page->get('preview')['created'])->toBe(1)
        ->and($page->get('preview')['updated'])->toBe(1);

    $page->call('import');

    expect($page->get('result')['updated'])->toBe(1)
        ->and(ProductionOrder::query()->where('number', 'ADA-1')->value('quantity'))->toBe(500)
        ->and(ProductionOrder::count())->toBe(2);
});

it('lets admin import too', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload([templateRow(['number' => 'ADM-1'])])])
        ->call('check')
        ->call('import');

    expect($page->get('result')['created'])->toBe(1);
});

it('refuses a file with more than 1000 rows and imports nothing', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload(array_fill(0, 1001, templateRow()))])
        ->call('check');

    expect($page->get('preview')['file_errors'][0])->toContain('Maksimal')
        ->and($page->get('preview')['can_import'])->toBeFalse();

    $page->call('import');

    expect(ProductionOrder::count())->toBe(0);
});

it('reports a file that is not a spreadsheet', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $upload = UploadedFile::fake()->createWithContent('order.xlsx', 'bukan excel');

    $page = Livewire::test(ImportOrders::class)->fillForm(['file' => $upload])->call('check');

    expect($page->get('preview')['file_errors'][0])->toContain('tidak bisa dibaca');
});

it('asks for a file before checking', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ImportOrders::class)->call('check')->assertHasFormErrors(['file' => 'required']);
});

it('does nothing when there is nothing importable', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $page = Livewire::test(ImportOrders::class)
        ->fillForm(['file' => xlsxUpload([templateRow(['customer' => 'XXX'])])])
        ->call('check');

    expect($page->get('preview')['can_import'])->toBeFalse();

    $page->call('import');

    expect(ProductionOrder::count())->toBe(0)
        ->and($page->get('result'))->toBeNull();
});

it('does not let anyone else run the check or the import', function (): void {
    $this->actingAs(User::factory()->supervisor()->create());

    Livewire::test(ImportOrders::class)->assertForbidden();
});

it('downloads a template the importer itself can read', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ImportOrders::class)->callAction('downloadTemplate')->assertFileDownloaded('templat-order.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'tpl');
    app(OrderSheetWriter::class)->writeTemplate($path);

    $read = (new OrderSheetReader)->read($path);

    expect($read['errors'])->toBe([])
        ->and($read['rows'])->toHaveCount(2)
        ->and($read['rows'][2]['customer_code'])->toBe('PLG-001')
        ->and($read['rows'][2]['product_code'])->toBe('PRD-001');

    @unlink($path);
});

it('gives a template whose example rows import cleanly into a fresh installation', function (): void {
    $user = User::factory()->manager()->create();

    $path = tempnam(sys_get_temp_dir(), 'tpl');
    app(OrderSheetWriter::class)->writeTemplate($path);

    $read = (new OrderSheetReader)->read($path);
    $report = app(OrderImporter::class)->analyze($read['rows'], ImportMode::AddOnly);

    expect($report->rejected())->toBe(0)
        ->and($report->created())->toBe(2);

    app(OrderImporter::class)->commit($report, $user);

    expect(ProductionOrder::count())->toBe(2);

    @unlink($path);
});
