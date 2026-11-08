<?php

namespace App\Services\Export;

use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\Import\OrderSheetReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes the Excel files of the order list: the empty template for importing, and the export of the current list.
 * The first eleven columns of the export are the same as the template, so an export can be edited and imported again.
 */
class OrderSheetWriter
{
    /**
     * @var array<int, string>
     */
    public const TEMPLATE_COLUMNS = [
        'Nomor order',
        'Kode pelanggan',
        'Kode produk',
        'Jumlah (pasang)',
        'Tanggal masuk',
        'Tenggat',
        'Harga satuan (Rp)',
        'Status',
        'Bahan tersedia (%)',
        'Sisa waktu pengerjaan (jam)',
        'Progres (%)',
    ];

    /**
     * @var array<int, string>
     */
    public const EXTRA_COLUMNS = ['Pelanggan', 'Produk', 'Sisa hari', 'Nilai order (Rp)', 'Prioritas', 'Kunci'];

    private const DATE_FORMAT = 'dd/mm/yyyy';

    public function writeTemplate(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(OrderSheetReader::SHEET_NAME);

        $writer->addRow($this->headerRow(self::TEMPLATE_COLUMNS));

        $customer = Customer::query()->where('is_active', true)->orderBy('code')->first();
        $product = Product::query()->where('is_active', true)->orderBy('code')->first();
        $customerCode = $customer?->code ?? 'PLG-001';
        $productCode = $product?->code ?? 'PRD-001';

        $writer->addRow($this->dataRow(['', $customerCode, $productCode, 100, today(), today()->addDays(14), '', 'Antri', 80, 24, 0]));
        $writer->addRow($this->dataRow(['', $customerCode, $productCode, 250, today()->subDays(2), today()->addDays(21), '', 'Antri', '', '', '']));

        $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');

        foreach ($this->instructions() as $line) {
            $writer->addRow(Row::fromValues([$line]));
        }

        $writer->close();
    }

    /**
     * A sample file for trying the import with the demo data: twenty good rows and two broken ones.
     * The broken rows are row 22 (unknown customer) and row 23 (deadline before entry date and material above 100).
     */
    public function writeSample(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(OrderSheetReader::SHEET_NAME);
        $writer->addRow($this->headerRow(self::TEMPLATE_COLUMNS));

        $customers = ['PLG-001', 'PLG-002', 'PLG-003', 'PLG-004', 'PLG-005'];
        $products = ['PRD-001', 'PRD-002', 'PRD-003', 'PRD-004'];

        for ($n = 1; $n <= 20; $n++) {
            $writer->addRow($this->dataRow([
                '', $customers[$n % 5], $products[$n % 4], 60 + $n * 5,
                today()->subDays($n % 6), today()->addDays(5 + $n), '',
                $n % 4 === 0 ? 'Dikerjakan' : 'Antri', 40 + $n * 2, 10 + $n * 3, $n * 3,
            ]));
        }

        $writer->addRow($this->dataRow(['', 'PLG-XYZ', 'PRD-001', 100, today(), today()->addDays(10), '', 'Antri', '', '', '']));
        $writer->addRow($this->dataRow(['', 'PLG-001', 'PRD-002', 80, today()->addDays(10), today(), '', 'Antri', 130, '', '']));

        $writer->close();
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     */
    public function writeOrders(string $path, Builder $query): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(OrderSheetReader::SHEET_NAME);

        $writer->addRow($this->headerRow([...self::TEMPLATE_COLUMNS, ...self::EXTRA_COLUMNS]));

        $criteria = Criterion::query()->whereIn('code', ['C3', 'C4', 'C5'])->pluck('id', 'code');

        foreach ($query->with(['customer', 'product', 'assessments'])->get() as $order) {
            $writer->addRow($this->dataRow($this->orderCells($order, $criteria)));
        }

        $writer->close();
    }

    /**
     * @param  Collection<string, int>  $criteria  criterion id by code
     * @return array<int, mixed>
     */
    private function orderCells(ProductionOrder $order, $criteria): array
    {
        $manual = fn (string $code): ?float => $criteria->has($code)
            ? ($order->assessments->firstWhere('criterion_id', $criteria[$code])?->raw_value === null ? null : (float) $order->assessments->firstWhere('criterion_id', $criteria[$code])->raw_value)
            : null;

        return [
            $order->number,
            $order->customer->code,
            $order->product->code,
            $order->quantity,
            $order->order_date,
            $order->deadline_date,
            $order->unit_price === null ? '' : (float) $order->unit_price,
            $order->status->label(),
            $manual('C3'),
            $manual('C4'),
            $manual('C5'),
            $order->customer->name,
            $order->product->name,
            $order->status->isOpen() ? $order->daysLeft() : '',
            $order->totalValue() ?? '',
            $order->priority->label(),
            $order->isLocked() ? $order->lock_type->label() : '',
        ];
    }

    /**
     * @param  array<int, string>  $titles
     */
    private function headerRow(array $titles): Row
    {
        $style = (new Style)->setFontBold();

        return new Row(array_map(fn (string $title): Cell => Cell::fromValue($title, $style), $titles));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function dataRow(array $values): Row
    {
        $dateStyle = (new Style)->setFormat(self::DATE_FORMAT);

        return new Row(array_map(function (mixed $value) use ($dateStyle): Cell {
            if ($value instanceof \DateTimeInterface) {
                return Cell::fromValue($value, $dateStyle);
            }

            return Cell::fromValue($value === null ? '' : $value);
        }, $values));
    }

    /**
     * @return array<int, string>
     */
    private function instructions(): array
    {
        return [
            'PETUNJUK PENGISIAN',
            '',
            'Isi data pada lembar Order. Jangan mengubah judul kolom di baris pertama dan jangan mengubah nama lembar Order.',
            'Hapus dua baris contoh sebelum mengisi data Anda. Satu berkas memuat paling banyak '.OrderSheetReader::MAX_ROWS.' baris order.',
            '',
            'Kolom wajib: Kode pelanggan, Kode produk, Jumlah, Tanggal masuk, Tenggat.',
            '  Kode pelanggan dan Kode produk harus sama dengan kode di menu Data Master. Pelanggan atau produk yang nonaktif tidak bisa dipakai untuk order baru.',
            '  Jumlah berupa bilangan bulat lebih dari 0.',
            '  Tanggal masuk dan Tenggat berupa tanggal Excel atau tulisan hari/bulan/tahun, misalnya 25/12/2026. Tenggat tidak boleh sebelum tanggal masuk.',
            '',
            'Kolom boleh kosong: Nomor order, Harga satuan, Status, Bahan tersedia, Sisa waktu pengerjaan, Progres.',
            '  Nomor order kosong berarti nomor dibuat otomatis. Nomor yang sudah ada dipakai untuk memperbarui order bila Anda memilih mode perbarui.',
            '  Harga satuan kosong berarti memakai harga dari produk.',
            '  Status berisi salah satu dari: Antri, Dikerjakan, Selesai, Dibatalkan. Kosong berarti Antri.',
            '  Bahan tersedia dan Progres berupa persen 0 sampai 100. Sisa waktu pengerjaan berupa jam, tidak boleh negatif.',
            '',
            'Saat memperbarui order yang sudah ada, kolom wajib selalu menimpa data lama. Kolom yang boleh kosong dibiarkan seperti semula bila Anda mengosongkannya.',
        ];
    }
}
