<?php

use App\Enums\ImportMode;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\AssessmentHistory;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use App\Services\Import\ImportReport;
use App\Services\Import\OrderImporter;
use App\Services\Import\OrderSheetReader;
use App\Services\Import\RowResult;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);

    $this->customer = Customer::factory()->create(['code' => 'PLG-001']);
    $this->otherCustomer = Customer::factory()->create(['code' => 'PLG-002']);
    $this->inactiveCustomer = Customer::factory()->inactive()->create(['code' => 'PLG-OFF']);
    $this->product = Product::factory()->create(['code' => 'PRD-001', 'unit_price' => 150000]);
    $this->unpricedProduct = Product::factory()->create(['code' => 'PRD-002', 'unit_price' => null]);
    $this->inactiveProduct = Product::factory()->inactive()->create(['code' => 'PRD-OFF']);
    $this->user = User::factory()->manager()->create();
    $this->importer = app(OrderImporter::class);
});

/**
 * Runs a list of template rows through the real file reader and the importer.
 *
 * @param  array<int, array<int, mixed>>  $rows  template rows without the header
 */
function analyseSheet(array $rows, ImportMode $mode = ImportMode::AddOnly): ImportReport
{
    $read = (new OrderSheetReader)->read(makeXlsx([templateHeader(), ...$rows]));

    expect($read['errors'])->toBe([]);

    return app(OrderImporter::class)->analyze($read['rows'], $mode);
}

it('imports twenty valid orders with their assessment values filled in', function (): void {
    $rows = [];

    foreach (range(1, 20) as $n) {
        $rows[] = templateRow(['quantity' => 50 + $n, 'material' => 40 + $n, 'hours' => 10 + $n, 'progress' => $n]);
    }

    $report = analyseSheet($rows);

    expect($report->created())->toBe(20)
        ->and($report->rejected())->toBe(0)
        ->and($report->canImport())->toBeTrue();

    $result = $this->importer->commit($report, $this->user);

    expect($result)->toBe(['created' => 20, 'updated' => 0])
        ->and(ProductionOrder::count())->toBe(20)
        ->and(Assessment::count())->toBe(60)
        ->and(AssessmentHistory::count())->toBe(60)
        ->and(ProductionOrder::query()->where('created_by', $this->user->id)->count())->toBe(20);

    $first = ProductionOrder::query()->orderBy('id')->first();
    $values = $first->assessments()->with('criterion')->get()->mapWithKeys(fn (Assessment $a): array => [$a->criterion->code => (float) $a->raw_value]);

    expect($values->all())->toBe(['C3' => 41.0, 'C4' => 11.0, 'C5' => 1.0]);
});

it('shows two messages for two broken rows and saves the rest without them', function (): void {
    $rows = [];

    foreach (range(1, 18) as $n) {
        $rows[] = templateRow(['number' => "OK-{$n}"]);
    }

    $rows[] = templateRow(['number' => 'RUSAK-1', 'customer' => 'PLG-TIDAK-ADA']);
    $rows[] = templateRow(['number' => 'RUSAK-2', 'quantity' => 0]);

    $report = analyseSheet($rows);

    expect($report->created())->toBe(18)
        ->and($report->rejected())->toBe(2);

    $broken = array_values(array_filter($report->rows, fn (RowResult $row): bool => $row->action === RowResult::ERROR));

    expect($broken[0]->row)->toBe(20)
        ->and($broken[0]->messages[0])->toContain('PLG-TIDAK-ADA')->toContain('tidak dikenal')
        ->and($broken[1]->row)->toBe(21)
        ->and($broken[1]->messages[0])->toContain('Jumlah');

    $this->importer->commit($report, $this->user);

    expect(ProductionOrder::count())->toBe(18)
        ->and(ProductionOrder::query()->whereIn('number', ['RUSAK-1', 'RUSAK-2'])->exists())->toBeFalse();
});

it('rejects an unknown or inactive customer and product with a clear message', function (): void {
    $report = analyseSheet([
        templateRow(['customer' => 'XXX']),
        templateRow(['product' => 'YYY']),
        templateRow(['customer' => 'PLG-OFF']),
        templateRow(['product' => 'PRD-OFF']),
        templateRow(['customer' => '']),
    ]);

    $messages = collect($report->rows)->map(fn (RowResult $row): string => $row->messages[0] ?? '')->all();

    expect($report->rejected())->toBe(5)
        ->and($messages[0])->toBe('Kode pelanggan "XXX" tidak dikenal.')
        ->and($messages[1])->toBe('Kode produk "YYY" tidak dikenal.')
        ->and($messages[2])->toBe('Pelanggan "PLG-OFF" sudah nonaktif.')
        ->and($messages[3])->toBe('Produk "PRD-OFF" sudah nonaktif.')
        ->and($messages[4])->toBe('Kode pelanggan kosong.');
});

it('matches codes without caring about capitals or spaces', function (): void {
    $report = analyseSheet([templateRow(['customer' => ' plg-001 ', 'product' => 'prd-001'])]);

    expect($report->created())->toBe(1);
});

it('accepts only whole quantities above zero', function (mixed $quantity, bool $valid): void {
    $report = analyseSheet([templateRow(['quantity' => $quantity])]);

    expect($report->created())->toBe($valid ? 1 : 0)
        ->and($report->rejected())->toBe($valid ? 0 : 1);
})->with([
    'zero' => [0, false],
    'negative' => [-5, false],
    'fraction' => [2.5, false],
    'text' => ['banyak', false],
    'one' => [1, true],
    'text number' => ['100', true],
    'whole float' => [100.0, true],
]);

it('understands several ways of writing a date', function (string $entered, string $deadline, bool $valid): void {
    $report = analyseSheet([templateRow(['entered' => $entered, 'deadline' => $deadline])]);

    expect($report->created())->toBe($valid ? 1 : 0);
})->with([
    'day month year' => ['05/03/2027', '20/03/2027', true],
    'dashes' => ['05-03-2027', '20-03-2027', true],
    'iso' => ['2027-03-05', '2027-03-20', true],
    'dots' => ['05.03.2027', '20.03.2027', true],
    'single digits' => ['5/3/2027', '2/4/2027', true],
    'words' => ['kemarin', '2027-03-20', false],
    'impossible day' => ['31/02/2027', '2027-03-20', false],
    'blank' => ['', '2027-03-20', false],
    'deadline before entry' => ['2027-03-20', '2027-03-05', false],
    'same day' => ['2027-03-05', '2027-03-05', true],
]);

it('reads the dates of an Excel date cell and of an Excel serial number', function (): void {
    $importer = $this->importer;

    $report = $importer->analyze([
        2 => ['number' => null, 'customer_code' => 'PLG-001', 'product_code' => 'PRD-001', 'quantity' => 10, 'order_date' => new DateTimeImmutable('2027-03-05'), 'deadline_date' => 46465],
    ], ImportMode::AddOnly);

    $row = $report->rows[0];

    expect($row->action)->toBe(RowResult::CREATE)
        ->and($row->data['order_date'])->toBe('2027-03-05')
        ->and($row->data['deadline_date'])->toBe('2027-03-19');

    $anchor = $importer->analyze([
        2 => ['number' => null, 'customer_code' => 'PLG-001', 'product_code' => 'PRD-001', 'quantity' => 10, 'order_date' => 45292, 'deadline_date' => '2024-02-01'],
    ], ImportMode::AddOnly);

    expect($anchor->rows[0]->data['order_date'])->toBe('2024-01-01');
});

it('takes the price from the product when the price is blank and from the file when given', function (): void {
    $report = analyseSheet([
        templateRow(['number' => 'P-1']),
        templateRow(['number' => 'P-2', 'price' => '200.000']),
        templateRow(['number' => 'P-3', 'price' => 'Rp 175.500']),
        templateRow(['number' => 'P-4', 'product' => 'PRD-002']),
        templateRow(['number' => 'P-5', 'price' => 0]),
    ]);

    $this->importer->commit($report, $this->user);

    $price = fn (string $number) => ProductionOrder::query()->where('number', $number)->value('unit_price');

    expect($price('P-1'))->toEqual('150000.00')
        ->and($price('P-2'))->toEqual('200000.00')
        ->and($price('P-3'))->toEqual('175500.00')
        ->and($price('P-4'))->toBeNull()
        ->and($price('P-5'))->toEqual('0.00');
});

it('rejects a negative or non numeric price', function (): void {
    $report = analyseSheet([templateRow(['price' => -1]), templateRow(['price' => 'mahal'])]);

    expect($report->rejected())->toBe(2)
        ->and($report->rows[0]->messages[0])->toContain('Harga satuan');
});

it('maps the status by its label or value and defaults to queued', function (): void {
    $report = analyseSheet([
        templateRow(['number' => 'S-1', 'status' => '']),
        templateRow(['number' => 'S-2', 'status' => 'dikerjakan']),
        templateRow(['number' => 'S-3', 'status' => 'DONE']),
        templateRow(['number' => 'S-4', 'status' => 'Dibatalkan']),
    ]);

    $this->importer->commit($report, $this->user);

    $status = fn (string $number): OrderStatus => ProductionOrder::query()->where('number', $number)->firstOrFail()->status;

    expect($status('S-1'))->toBe(OrderStatus::Queued)
        ->and($status('S-2'))->toBe(OrderStatus::InProgress)
        ->and($status('S-3'))->toBe(OrderStatus::Done)
        ->and($status('S-4'))->toBe(OrderStatus::Canceled);
});

it('rejects an unknown status and lists the choices', function (): void {
    $report = analyseSheet([templateRow(['status' => 'Setengah jadi'])]);

    expect($report->rows[0]->action)->toBe(RowResult::ERROR)
        ->and($report->rows[0]->messages[0])->toContain('Setengah jadi')->toContain('Antri, Dikerjakan, Selesai, Dibatalkan');
});

it('checks the assessment columns against the same limits as the matrix', function (): void {
    $report = analyseSheet([
        templateRow(['material' => 120]),
        templateRow(['hours' => -3]),
        templateRow(['progress' => 'banyak']),
        templateRow(['material' => '79,5', 'hours' => 0, 'progress' => 100]),
    ]);

    expect($report->rows[0]->messages[0])->toContain('Bahan tersedia')->toContain('0 dan 100')
        ->and($report->rows[1]->messages[0])->toContain('Sisa waktu pengerjaan')->toContain('negatif')
        ->and($report->rows[2]->messages[0])->toContain('Progres')->toContain('angka')
        ->and($report->rows[3]->action)->toBe(RowResult::CREATE)
        ->and($report->rows[3]->data['values'])->toBe(['C3' => 79.5, 'C4' => 0.0, 'C5' => 100.0]);
});

it('leaves assessment cells empty when the columns are blank', function (): void {
    $report = analyseSheet([templateRow()]);

    $this->importer->commit($report, $this->user);

    expect(Assessment::count())->toBe(0);
});

it('creates sequential numbers for rows without a number', function (): void {
    $report = analyseSheet([templateRow(), templateRow(), templateRow(['number' => 'SENDIRI-1'])]);

    $this->importer->commit($report, $this->user);

    $year = today()->format('Y');

    expect(ProductionOrder::query()->orderBy('id')->pluck('number')->all())->toBe(["ORD-{$year}-0001", "ORD-{$year}-0002", 'SENDIRI-1']);
});

it('rejects the second use of a number inside one file', function (): void {
    $report = analyseSheet([templateRow(['number' => 'DUP-1']), templateRow(['number' => 'dup-1'])]);

    expect($report->rows[0]->action)->toBe(RowResult::CREATE)
        ->and($report->rows[1]->action)->toBe(RowResult::ERROR)
        ->and($report->rows[1]->messages[0])->toContain('baris 2');
});

it('skips orders whose number already exists when adding only', function (): void {
    ProductionOrder::factory()->create(['number' => 'ADA-1', 'quantity' => 10]);

    $report = analyseSheet([templateRow(['number' => 'ADA-1', 'quantity' => 999]), templateRow(['number' => 'BARU-1'])]);

    expect($report->skipped())->toBe(1)
        ->and($report->created())->toBe(1)
        ->and($report->rows[0]->action)->toBe(RowResult::SKIP)
        ->and($report->rows[0]->messages[0])->toContain('ADA-1')->toContain('dilewati');

    $result = $this->importer->commit($report, $this->user);

    expect($result)->toBe(['created' => 1, 'updated' => 0])
        ->and(ProductionOrder::query()->where('number', 'ADA-1')->value('quantity'))->toBe(10);
});

it('updates the orders whose number exists when updating is chosen', function (): void {
    $order = ProductionOrder::factory()->create([
        'number' => 'ADA-1', 'customer_id' => $this->customer->id, 'product_id' => $this->product->id,
        'quantity' => 10, 'unit_price' => 120000, 'status' => OrderStatus::Queued,
    ]);

    $report = analyseSheet([
        templateRow(['number' => 'ADA-1', 'customer' => 'PLG-002', 'quantity' => 400, 'deadline' => today()->addDays(30)->format('Y-m-d'), 'price' => 130000, 'status' => 'Dikerjakan', 'hours' => 12]),
    ], ImportMode::AddOrUpdate);

    expect($report->updated())->toBe(1)
        ->and($report->created())->toBe(0);

    $result = $this->importer->commit($report, $this->user);
    $order->refresh();

    expect($result)->toBe(['created' => 0, 'updated' => 1])
        ->and($order->customer_id)->toBe($this->otherCustomer->id)
        ->and($order->quantity)->toBe(400)
        ->and($order->unit_price)->toEqual('130000.00')
        ->and($order->status)->toBe(OrderStatus::InProgress)
        ->and($order->deadline_date->toDateString())->toBe(today()->addDays(30)->toDateString())
        ->and(ProductionOrder::count())->toBe(1)
        ->and((float) $order->assessments()->first()->raw_value)->toBe(12.0)
        ->and($order->activities()->where('action', 'order.updated')->exists())->toBeTrue();
});

it('keeps blank optional columns as they were when updating', function (): void {
    $order = ProductionOrder::factory()->create([
        'number' => 'ADA-1', 'customer_id' => $this->customer->id, 'product_id' => $this->product->id,
        'unit_price' => 120000, 'status' => OrderStatus::InProgress,
    ]);
    app(AssessmentWriter::class)->setManual($order, Criterion::query()->where('code', 'C3')->first(), 55, $this->user);

    $report = analyseSheet([templateRow(['number' => 'ADA-1', 'price' => '', 'status' => '', 'material' => ''])], ImportMode::AddOrUpdate);

    $this->importer->commit($report, $this->user);
    $order->refresh();

    expect($order->unit_price)->toEqual('120000.00')
        ->and($order->status)->toBe(OrderStatus::InProgress)
        ->and((float) $order->assessments()->first()->raw_value)->toBe(55.0);
});

it('lets an update keep a customer that was deactivated after the order was made', function (): void {
    ProductionOrder::factory()->create(['number' => 'LAMA-1', 'customer_id' => $this->inactiveCustomer->id, 'product_id' => $this->product->id]);

    $report = analyseSheet([templateRow(['number' => 'LAMA-1', 'customer' => 'PLG-OFF', 'quantity' => 77])], ImportMode::AddOrUpdate);

    expect($report->updated())->toBe(1);
});

it('rejects a number that belongs to a deleted order', function (): void {
    $order = ProductionOrder::factory()->create(['number' => 'HAPUS-1']);
    $order->delete();

    foreach ([ImportMode::AddOnly, ImportMode::AddOrUpdate] as $mode) {
        $report = analyseSheet([templateRow(['number' => 'HAPUS-1'])], $mode);

        expect($report->rows[0]->action)->toBe(RowResult::ERROR)
            ->and($report->rows[0]->messages[0])->toContain('dihapus');
    }
});

it('saves nothing at all when saving fails halfway', function (): void {
    $report = analyseSheet([
        templateRow(['number' => 'A-1']),
        templateRow(['number' => 'A-2']),
        templateRow(['number' => 'A-3', 'customer' => 'PLG-002']),
    ]);

    $this->otherCustomer->delete();

    expect(fn () => $this->importer->commit($report, $this->user))->toThrow(QueryException::class);

    expect(ProductionOrder::count())->toBe(0)
        ->and(Assessment::count())->toBe(0)
        ->and(ActivityLog::query()->where('subject_type', 'production_orders')->count())->toBe(0);
});

it('mentions a skipped column when its criterion is not available', function (): void {
    Criterion::query()->where('code', 'C3')->update(['is_active' => false]);

    $report = analyseSheet([templateRow(['material' => 50, 'hours' => 10])]);

    expect($report->notes)->toHaveCount(1)
        ->and($report->notes[0])->toContain('Bahan tersedia')->toContain('C3')
        ->and($report->rows[0]->data['values'])->toBe(['C4' => 10.0]);
});

it('imports nothing and reports nothing importable when every row is bad', function (): void {
    $report = analyseSheet([templateRow(['customer' => 'XXX']), templateRow(['quantity' => 0])]);

    expect($report->canImport())->toBeFalse()
        ->and($report->importableCount())->toBe(0);
});
