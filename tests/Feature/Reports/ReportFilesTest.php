<?php

use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\Report\CompanyProfile;
use App\Services\Report\ReportBuilder;
use App\Services\Report\ReportColumn;
use App\Services\Report\ReportDocument;
use App\Services\Report\ReportFormatter;
use App\Services\Report\ReportPdf;
use App\Services\Report\ReportTable;
use App\Services\Report\ReportXlsx;
use Database\Seeders\CriteriaSeeder;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();
    $this->path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
});

afterEach(function (): void {
    if (isset($this->path) && is_file($this->path)) {
        unlink($this->path);
    }
});

function fileEvaluation(int $orders = 5): Evaluation
{
    foreach (range(1, $orders) as $n) {
        $order = ProductionOrder::factory()->dueInDays(2 + $n * 3)->create(['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100]);

        foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
            Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => test()->criteria[$code]->id, 'raw_value' => $value, 'value_updated_at' => now()]);
        }
    }

    return app(EvaluationService::class)->run(test()->manager);
}

/**
 * @return array<string, array<int, array<int, mixed>>> rows by sheet name
 */
function readSheets(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        $sheets[$sheet->getName()] = $rows;
    }

    $reader->close();

    return $sheets;
}

/**
 * What the screen and the PDF show for a cell of the Excel file: the same value passed through the same formatter.
 */
function shown(ReportTable $table, array $row): array
{
    return array_map(function ($cell, $index) use ($table): string {
        return ReportFormatter::cell($table->columns[$index], $cell);
    }, $row, array_keys($row));
}

it('writes the Excel file with the letterhead sheet and one sheet per table', function (): void {
    Setting::put('company_name', 'PT Contoh Sepatu');
    Setting::put('company_address', 'Jl. Industri No. 5');
    $document = (new ReportBuilder)->ranking(fileEvaluation(), $this->manager);

    (new ReportXlsx)->write($document, $this->path);
    $sheets = readSheets($this->path);

    expect(array_keys($sheets))->toBe(['Ringkasan', 'Peringkat prioritas', 'Kriteria dan bobot'])
        ->and($sheets['Ringkasan'][0][0])->toBe('PT Contoh Sepatu')
        ->and($sheets['Ringkasan'][1][0])->toBe('Jl. Industri No. 5')
        ->and(collect($sheets['Ringkasan'])->pluck(0)->all())->toContain('Laporan Peringkat Prioritas Produksi', 'Dicetak oleh');
});

it('keeps the same numbers in the Excel ranking as on the screen', function (): void {
    $document = (new ReportBuilder)->ranking(fileEvaluation(6), $this->manager);
    (new ReportXlsx)->write($document, $this->path);

    $table = $document->tables[0];
    $rows = readSheets($this->path)['Peringkat prioritas'];

    expect($rows[0])->toBe($table->headings())->and(count($rows))->toBeGreaterThanOrEqual(1 + 6);

    foreach ($table->displayRows() as $index => $display) {
        expect(shown($table, $rows[$index + 1]))->toBe($display);
    }
});

it('writes numbers and dates as real numbers and dates', function (): void {
    $document = (new ReportBuilder)->ranking(fileEvaluation(3), $this->manager);
    (new ReportXlsx)->write($document, $this->path);
    $row = readSheets($this->path)['Peringkat prioritas'][1];

    expect($row[0])->toBeInt()
        ->and($row[4])->toBeInstanceOf(DateTimeInterface::class)
        ->and($row[6])->not->toBeString()
        ->and($row[7])->toBeInt();
});

it('keeps the same numbers in every sheet of the calculation report', function (): void {
    $document = (new ReportBuilder)->calculation(fileEvaluation(5), $this->manager);
    (new ReportXlsx)->write($document, $this->path);
    $sheets = readSheets($this->path);

    expect(array_keys($sheets))->toHaveCount(1 + count($document->tables));

    foreach ($document->tables as $table) {
        $name = array_keys($sheets)[1 + array_search($table, $document->tables, true)];
        $rows = $sheets[$name];

        expect($rows[0])->toBe($table->headings());

        foreach ($table->displayRows() as $index => $display) {
            expect(shown($table, $rows[$index + 1]))->toBe($display);
        }
    }
});

it('writes the totals row of a recap after the data rows', function (): void {
    ProductionOrder::factory()->create(['order_date' => today()]);
    $document = (new ReportBuilder)->ordersRecap(today()->subDay(), today(), $this->manager);
    (new ReportXlsx)->write($document, $this->path);
    $rows = readSheets($this->path)['Rekap per status'];

    expect($rows[0])->toBe(['Status', 'Jumlah order', 'Jumlah (pasang)'])
        ->and(end($rows)[0])->toBe('Total');
});

it('gives every sheet a valid unique name', function (): void {
    $document = new ReportDocument(
        'uji', 'Uji', [], [
            new ReportTable('Rekap: per [status]/pelanggan yang sangat panjang sekali', [ReportColumn::text('A')], [['x']]),
            new ReportTable('Rekap: per [status]/pelanggan yang sangat panjang sekali', [ReportColumn::text('A')], [['y']]),
        ],
        CompanyProfile::load(), 'Penguji', now(),
    );

    (new ReportXlsx)->write($document, $this->path);
    $names = array_keys(readSheets($this->path));

    expect($names)->toHaveCount(3)->and(array_unique($names))->toHaveCount(3);

    foreach ($names as $name) {
        expect(mb_strlen($name))->toBeLessThanOrEqual(31)->and($name)->not->toMatch('/[\[\]:*?\/\\\\]/');
    }
});

it('draws the PDF from the same rows as the screen, with the letterhead and the signature block', function (): void {
    Setting::put('company_name', 'PT Contoh Sepatu');
    Setting::put('company_address', 'Jl. Industri No. 5');
    $document = (new ReportBuilder)->ranking(fileEvaluation(5), $this->manager);

    $html = view('reports.pdf', ['document' => $document])->render();

    expect($html)->toContain('PT Contoh Sepatu')
        ->toContain('Jl. Industri No. 5')
        ->toContain('Laporan Peringkat Prioritas Produksi')
        ->toContain('Dicetak oleh')
        ->toContain($this->manager->name)
        ->toContain('Diketahui oleh')
        ->toContain('Pimpinan');

    foreach ($document->tables[0]->displayRows() as $row) {
        foreach ($row as $cell) {
            expect($html)->toContain(e($cell));
        }
    }
});

it('renders a real PDF file', function (): void {
    $document = (new ReportBuilder)->calculation(fileEvaluation(5), $this->manager);
    $pdf = (new ReportPdf)->render($document);

    expect(substr($pdf, 0, 5))->toBe('%PDF-')->and(strlen($pdf))->toBeGreaterThan(5000);
});

it('renders a PDF for a report without rows', function (): void {
    $document = (new ReportBuilder)->lateOrders(today()->subMonth(), today(), $this->manager);
    $pdf = (new ReportPdf)->render($document);

    expect(substr($pdf, 0, 5))->toBe('%PDF-');
    expect(view('reports.pdf', ['document' => $document])->render())->toContain('Tidak ada order terlambat');
});

it('escapes text from the data in the PDF', function (): void {
    $customer = Customer::factory()->create(['name' => 'Toko <b>Cahaya</b> & Anak']);
    ProductionOrder::factory()->create(['customer_id' => $customer->id, 'order_date' => today()->subDays(3), 'deadline_date' => today()->subDay()]);

    $document = (new ReportBuilder)->lateOrders(today()->subMonth(), today(), $this->manager);
    $html = view('reports.pdf', ['document' => $document])->render();

    expect($html)->not->toContain('<b>Cahaya</b>')->toContain('Toko &lt;b&gt;Cahaya&lt;/b&gt; &amp; Anak');
});
