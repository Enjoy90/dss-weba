<?php

use App\Services\Import\OrderSheetReader;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->paths = [];
});

afterEach(function (): void {
    foreach ($this->paths ?? [] as $path) {
        @unlink($path);
    }
});

function readSheet(array $rows, string $sheet = 'Order'): array
{
    return (new OrderSheetReader)->read(makeXlsx($rows, $sheet));
}

it('reads the template rows keyed by field name and Excel row number', function (): void {
    $result = readSheet([
        templateHeader(),
        templateRow(['number' => 'ORD-1', 'quantity' => 120, 'material' => 80, 'hours' => 24, 'progress' => 10]),
        templateRow(['customer' => 'PLG-002', 'product' => 'PRD-002']),
    ]);

    expect($result['errors'])->toBe([])
        ->and(array_keys($result['rows']))->toBe([2, 3])
        ->and($result['rows'][2]['number'])->toBe('ORD-1')
        ->and($result['rows'][2]['customer_code'])->toBe('PLG-001')
        ->and($result['rows'][2]['quantity'])->toEqual(120)
        ->and($result['rows'][2]['material'])->toEqual(80)
        ->and($result['rows'][3]['customer_code'])->toBe('PLG-002');
});

it('matches column titles whatever their capitals, brackets or wording', function (): void {
    $result = readSheet([
        ['NO ORDER', 'Pelanggan', 'KODE PRODUK', 'jumlah pasang', 'Tgl Masuk', 'Tanggal Tenggat', 'harga', 'status', 'bahan', 'sisa waktu', 'progress'],
        ['A-1', 'PLG-001', 'PRD-001', 5, '01/01/2027', '10/01/2027', 1000, 'Antri', 10, 20, 30],
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['rows'][2])->toHaveKeys(['number', 'customer_code', 'product_code', 'quantity', 'order_date', 'deadline_date', 'unit_price', 'status', 'material', 'hours', 'progress']);
});

it('skips blank rows but keeps the real row numbers', function (): void {
    $result = readSheet([
        templateHeader(),
        templateRow(),
        ['', '', '', '', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', '', '', '', ''],
        templateRow(['customer' => 'PLG-002']),
    ]);

    expect(array_keys($result['rows']))->toBe([2, 5]);
});

it('ignores columns it does not know', function (): void {
    $result = readSheet([
        [...templateHeader(), 'Catatan saya'],
        [...templateRow(), 'bebas'],
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['rows'][2])->not->toHaveKey('Catatan saya');
});

it('prefers the sheet named Order over the first sheet', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $this->paths[] = $path;

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Petunjuk');
    $writer->addRow(Row::fromValues(['Ini hanya petunjuk']));
    $writer->addNewSheetAndMakeItCurrent()->setName('Order');
    $writer->addRow(Row::fromValues(templateHeader()));
    $writer->addRow(Row::fromValues(templateRow()));
    $writer->close();

    $result = (new OrderSheetReader)->read($path);

    expect($result['errors'])->toBe([])
        ->and($result['rows'])->toHaveCount(1);
});

it('names the required columns that are missing', function (): void {
    $header = array_values(array_filter(templateHeader(), fn (string $title): bool => ! str_starts_with($title, 'Tenggat') && ! str_starts_with($title, 'Kode produk')));

    $result = readSheet([$header, ['', 'PLG-001', 100, '2027-01-01']]);

    expect($result['rows'])->toBe([])
        ->and($result['errors'][0])->toContain('Kode produk')->toContain('Tenggat');
});

it('refuses a file with more than 1000 rows and accepts exactly 1000', function (): void {
    $rows = fn (int $count): array => [templateHeader(), ...array_fill(0, $count, templateRow())];

    $tooMany = readSheet($rows(1001));
    $limit = readSheet($rows(1000));

    expect($tooMany['rows'])->toBe([])
        ->and($tooMany['errors'][0])->toContain('1.001')->toContain('1.000')
        ->and($limit['errors'])->toBe([])
        ->and($limit['rows'])->toHaveCount(1000);
});

it('reports a file that is not a spreadsheet', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'txt');
    $this->paths[] = $path;
    file_put_contents($path, 'ini bukan excel');

    $result = (new OrderSheetReader)->read($path);

    expect($result['rows'])->toBe([])
        ->and($result['errors'][0])->toContain('tidak bisa dibaca');
});

it('reports a file with a header and no orders', function (): void {
    $result = readSheet([templateHeader()]);

    expect($result['rows'])->toBe([])
        ->and($result['errors'][0])->toContain('tidak berisi baris order');
});

it('reports an empty sheet', function (): void {
    $result = readSheet([['', '', '']]);

    expect($result['errors'][0])->toContain('tidak berisi data');
});

it('hands over real dates from date cells', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $this->paths[] = $path;

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Order');
    $writer->addRow(Row::fromValues(templateHeader()));
    $style = (new Style)->setFormat('dd/mm/yyyy');
    $writer->addRow(new Row([
        Cell::fromValue(''),
        Cell::fromValue('PLG-001'),
        Cell::fromValue('PRD-001'),
        Cell::fromValue(10),
        Cell::fromValue(new DateTimeImmutable('2027-03-05'), $style),
        Cell::fromValue(new DateTimeImmutable('2027-03-20'), $style),
    ]));
    $writer->close();

    $result = (new OrderSheetReader)->read($path);

    expect($result['rows'][2]['order_date'])->toBeInstanceOf(DateTimeInterface::class)
        ->and($result['rows'][2]['order_date']->format('Y-m-d'))->toBe('2027-03-05')
        ->and($result['rows'][2]['deadline_date']->format('Y-m-d'))->toBe('2027-03-20');
});
