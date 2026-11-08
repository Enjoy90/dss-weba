<?php

namespace App\Services\Import;

use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Reads the order sheet of an Excel file into rows keyed by a fixed set of field names,
 * whatever the exact wording of the column titles.
 */
class OrderSheetReader
{
    public const MAX_ROWS = 1000;

    public const SHEET_NAME = 'Order';

    /**
     * @var array<int, string>
     */
    public const REQUIRED = ['customer_code', 'product_code', 'quantity', 'order_date', 'deadline_date'];

    /**
     * Normalised title (lower case, no brackets, only letters and digits) => field name.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'nomororder' => 'number',
        'nomor' => 'number',
        'noorder' => 'number',
        'kodepelanggan' => 'customer_code',
        'pelanggan' => 'customer_code',
        'kodeproduk' => 'product_code',
        'produk' => 'product_code',
        'jumlah' => 'quantity',
        'jumlahpasang' => 'quantity',
        'qty' => 'quantity',
        'tanggalmasuk' => 'order_date',
        'tglmasuk' => 'order_date',
        'tenggat' => 'deadline_date',
        'tanggaltenggat' => 'deadline_date',
        'deadline' => 'deadline_date',
        'hargasatuan' => 'unit_price',
        'harga' => 'unit_price',
        'status' => 'status',
        'bahantersedia' => 'material',
        'bahan' => 'material',
        'sisawaktupengerjaan' => 'hours',
        'sisawaktu' => 'hours',
        'progres' => 'progress',
        'progress' => 'progress',
    ];

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     *                                                                                   rows are keyed by the Excel row number so messages can point at the right line
     */
    public function read(string $path): array
    {
        try {
            return $this->readSheet($path);
        } catch (Throwable) {
            return ['rows' => [], 'errors' => ['Berkas tidak bisa dibaca. Pastikan berupa file Excel berformat .xlsx, misalnya hasil unduhan templat.']];
        }
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private function readSheet(string $path): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $reader->open($path);

        try {
            $sheetRows = $this->orderSheetRows($reader);
        } finally {
            $reader->close();
        }

        if ($sheetRows === []) {
            return ['rows' => [], 'errors' => ['Berkas tidak berisi data.']];
        }

        $header = $this->mapHeader(array_shift($sheetRows));
        $missing = array_diff(self::REQUIRED, array_values($header));

        if ($missing !== []) {
            return ['rows' => [], 'errors' => ['Kolom wajib tidak ditemukan: '.implode(', ', array_map(fn (string $field): string => $this->fieldTitle($field), $missing)).'. Gunakan templat dari halaman ini.']];
        }

        $rows = [];

        foreach ($sheetRows as $index => $cells) {
            if ($this->isBlankRow($cells)) {
                continue;
            }

            $row = [];

            foreach ($header as $column => $field) {
                $row[$field] = $cells[$column] ?? null;
            }

            $rows[$index + 2] = $row;
        }

        if ($rows === []) {
            return ['rows' => [], 'errors' => ['Berkas tidak berisi baris order.']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['rows' => [], 'errors' => ['Berkas berisi '.number_format(count($rows), 0, ',', '.').' baris. Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' baris per berkas. Bagi menjadi beberapa berkas.']];
        }

        return ['rows' => $rows, 'errors' => []];
    }

    /**
     * The sheet named "Order" when there is one, otherwise the first sheet.
     *
     * @return array<int, array<int, mixed>>
     */
    private function orderSheetRows(Reader $reader): array
    {
        $chosen = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($chosen === null) {
                $chosen = $sheet;
            }

            if (strcasecmp($sheet->getName(), self::SHEET_NAME) === 0) {
                $chosen = $sheet;

                break;
            }
        }

        if ($chosen === null) {
            return [];
        }

        $rows = [];

        foreach ($chosen->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        while ($rows !== [] && $this->isBlankRow(end($rows))) {
            array_pop($rows);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $titles
     * @return array<int, string> column index => field name
     */
    private function mapHeader(array $titles): array
    {
        $map = [];

        foreach ($titles as $column => $title) {
            $key = $this->normalise((string) $title);

            if (isset(self::ALIASES[$key]) && ! in_array(self::ALIASES[$key], $map, true)) {
                $map[$column] = self::ALIASES[$key];
            }
        }

        return $map;
    }

    private function normalise(string $title): string
    {
        $title = mb_strtolower($title);
        $title = preg_replace('/\([^)]*\)/u', '', $title) ?? $title;

        return preg_replace('/[^a-z0-9]/', '', $title) ?? '';
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) ($cell instanceof \DateTimeInterface ? $cell->format('c') : $cell)) !== '') {
                return false;
            }
        }

        return true;
    }

    private function fieldTitle(string $field): string
    {
        return match ($field) {
            'customer_code' => 'Kode pelanggan',
            'product_code' => 'Kode produk',
            'quantity' => 'Jumlah',
            'order_date' => 'Tanggal masuk',
            'deadline_date' => 'Tenggat',
            default => $field,
        };
    }
}
