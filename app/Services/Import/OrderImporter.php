<?php

namespace App\Services\Import;

use App\Enums\ImportMode;
use App\Enums\OrderStatus;
use App\Enums\ValueSource;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checks the rows of an order sheet and, once confirmed, saves them in a single transaction.
 *
 * A row that cannot be used is reported with its reason and left out. The rows that are fine are still imported.
 * If saving fails halfway, nothing at all is saved.
 */
class OrderImporter
{
    /**
     * Assessment values that can come with an order, by field name, criterion code and title.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const VALUE_COLUMNS = [
        'material' => ['C3', 'Bahan tersedia'],
        'hours' => ['C4', 'Sisa waktu pengerjaan'],
        'progress' => ['C5', 'Progres'],
    ];

    private const DATE_FORMATS = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'j/n/Y', 'j-n-Y'];

    public function __construct(private readonly AssessmentWriter $writer) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  keyed by the Excel row number
     */
    public function analyze(array $rows, ImportMode $mode): ImportReport
    {
        $customers = Customer::query()->get()->keyBy(fn (Customer $customer): string => mb_strtolower(trim($customer->code)));
        $products = Product::query()->get()->keyBy(fn (Product $product): string => mb_strtolower(trim($product->code)));
        $criteria = Criterion::query()->active()->get()->filter(fn (Criterion $criterion): bool => $criterion->value_source === ValueSource::Manual)->keyBy('code');

        $numbers = collect($rows)->map(fn (array $row): ?string => $this->text($row['number'] ?? null))->filter()->unique()->values()->all();
        $existing = ProductionOrder::withTrashed()->whereIn('number', $numbers)->get()->keyBy(fn (ProductionOrder $order): string => mb_strtolower($order->number));

        $results = [];
        $seen = [];
        $ignoredColumns = [];

        foreach ($rows as $rowNumber => $row) {
            $number = $this->text($row['number'] ?? null);
            $messages = [];

            if ($number !== null) {
                $key = mb_strtolower($number);

                if (isset($seen[$key])) {
                    $messages[] = "Nomor {$number} sudah dipakai di baris {$seen[$key]} berkas ini.";
                } else {
                    $seen[$key] = $rowNumber;
                }
            }

            /** @var ProductionOrder|null $order */
            $order = $number !== null ? $existing->get(mb_strtolower($number)) : null;

            if ($order?->trashed()) {
                $messages[] = "Nomor {$number} dipakai order yang sudah dihapus.";
            }

            if ($messages === [] && $order !== null && $mode === ImportMode::AddOnly) {
                $results[] = new RowResult($rowNumber, RowResult::SKIP, ["Nomor {$number} sudah ada, dilewati karena mode tambah saja."], [], $number);

                continue;
            }

            $data = $this->cleanRow($row, $order, $customers, $products, $criteria, $messages, $ignoredColumns);

            if ($messages !== []) {
                $results[] = new RowResult($rowNumber, RowResult::ERROR, $messages, [], $number);

                continue;
            }

            $results[] = new RowResult($rowNumber, $order === null ? RowResult::CREATE : RowResult::UPDATE, [], $data, $number);
        }

        return new ImportReport([], $results, $this->notes($ignoredColumns));
    }

    /**
     * Saves every row that passed the check. Returns how many were added and updated.
     *
     * @return array{created: int, updated: int}
     */
    public function commit(ImportReport $report, User $by): array
    {
        $created = 0;
        $updated = 0;
        $criteria = Criterion::query()->active()->get()->keyBy('code');

        DB::transaction(function () use ($report, $by, $criteria, &$created, &$updated): void {
            foreach ($report->rows as $row) {
                if (! $row->isImportable()) {
                    continue;
                }

                $order = $row->action === RowResult::CREATE
                    ? $this->createOrder($row->data, $by)
                    : $this->updateOrder($row->data);

                foreach ($row->data['values'] as $code => $value) {
                    if ($criteria->has($code)) {
                        $this->writer->setManual($order, $criteria[$code], $value, $by);
                    }
                }

                $row->action === RowResult::CREATE ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createOrder(array $data, User $by): ProductionOrder
    {
        return ProductionOrder::query()->create([
            'number' => $data['number'],
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'order_date' => $data['order_date'],
            'deadline_date' => $data['deadline_date'],
            'unit_price' => $data['unit_price_given'] ? $data['unit_price'] : $data['product_price'],
            'status' => $data['status'] ?? OrderStatus::Queued->value,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Required columns always overwrite. Optional columns left blank keep what the order already has.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateOrder(array $data): ProductionOrder
    {
        $order = ProductionOrder::query()->findOrFail($data['existing_id']);

        $changes = [
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'order_date' => $data['order_date'],
            'deadline_date' => $data['deadline_date'],
        ];

        if ($data['unit_price_given']) {
            $changes['unit_price'] = $data['unit_price'];
        }

        if ($data['status'] !== null) {
            $changes['status'] = $data['status'];
        }

        $order->update($changes);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<string, Customer>  $customers
     * @param  Collection<string, Product>  $products
     * @param  Collection<string, Criterion>  $criteria
     * @param  array<int, string>  $messages
     * @param  array<string, bool>  $ignoredColumns
     * @return array<string, mixed>
     */
    private function cleanRow(array $row, ?ProductionOrder $order, $customers, $products, $criteria, array &$messages, array &$ignoredColumns): array
    {
        $customer = $this->lookup('Kode pelanggan', $this->text($row['customer_code'] ?? null), $customers, 'Pelanggan', $order?->customer_id, $messages);
        $product = $this->lookup('Kode produk', $this->text($row['product_code'] ?? null), $products, 'Produk', $order?->product_id, $messages);

        $quantity = $this->parseNumber($row['quantity'] ?? null);

        if ($quantity === null || $quantity === false || $quantity < 1 || $quantity != floor($quantity)) {
            $messages[] = 'Jumlah harus bilangan bulat lebih dari 0.';
            $quantity = null;
        }

        $orderDate = $this->parseDate($row['order_date'] ?? null, 'Tanggal masuk', $messages);
        $deadline = $this->parseDate($row['deadline_date'] ?? null, 'Tenggat', $messages);

        if ($orderDate !== null && $deadline !== null && $deadline->lt($orderDate)) {
            $messages[] = 'Tenggat tidak boleh sebelum tanggal masuk.';
        }

        $price = $this->parseNumber($row['unit_price'] ?? null);

        if ($price === false || ($price !== null && $price < 0)) {
            $messages[] = 'Harga satuan harus angka 0 atau lebih.';
            $price = null;
        }

        $status = $this->parseStatus($row['status'] ?? null, $messages);

        $values = [];

        foreach (self::VALUE_COLUMNS as $field => [$code, $title]) {
            $value = $this->parseNumber($row[$field] ?? null);

            if ($value === null) {
                continue;
            }

            if ($value === false) {
                $messages[] = "{$title} harus berupa angka.";

                continue;
            }

            if (! $criteria->has($code)) {
                $ignoredColumns[$field] = true;

                continue;
            }

            try {
                $this->writer->validateValue($criteria[$code], $value);
                $values[$code] = $value;
            } catch (ValidationException $exception) {
                $messages[] = "{$title}: ".collect($exception->errors())->flatten()->first();
            }
        }

        return [
            'number' => $this->text($row['number'] ?? null),
            'existing_id' => $order?->id,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name,
            'product_id' => $product?->id,
            'product_name' => $product?->name,
            'product_price' => $product?->unit_price === null ? null : (float) $product->unit_price,
            'quantity' => $quantity === null ? null : (int) $quantity,
            'order_date' => $orderDate?->toDateString(),
            'deadline_date' => $deadline?->toDateString(),
            'unit_price' => $price,
            'unit_price_given' => $price !== null,
            'status' => $status,
            'values' => $values,
        ];
    }

    /**
     * @param  Collection<string, Customer|Product>  $records
     * @param  array<int, string>  $messages
     */
    private function lookup(string $title, ?string $code, $records, string $noun, ?int $currentId, array &$messages): Customer|Product|null
    {
        if ($code === null) {
            $messages[] = "{$title} kosong.";

            return null;
        }

        $record = $records->get(mb_strtolower($code));

        if ($record === null) {
            $messages[] = "{$title} \"{$code}\" tidak dikenal.";

            return null;
        }

        if (! $record->is_active && $record->id !== $currentId) {
            $messages[] = "{$noun} \"{$code}\" sudah nonaktif.";

            return null;
        }

        return $record;
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function parseStatus(mixed $value, array &$messages): ?string
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        foreach (OrderStatus::cases() as $status) {
            if (strcasecmp($text, $status->label()) === 0 || strcasecmp($text, $status->value) === 0) {
                return $status->value;
            }
        }

        $messages[] = "Status \"{$text}\" tidak dikenal. Pilihan: ".implode(', ', array_map(fn (OrderStatus $status): string => $status->label(), OrderStatus::cases())).'.';

        return null;
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function parseDate(mixed $value, string $title, array &$messages): ?Carbon
    {
        $date = $this->toDate($value);

        if ($date === null) {
            $messages[] = "{$title} kosong atau formatnya tidak dikenal. Gunakan tanggal Excel atau tulisan hari/bulan/tahun, misalnya 25/12/2026.";
        }

        return $date;
    }

    private function toDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return $this->sane(Carbon::instance($value)->startOfDay());
        }

        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        if (is_numeric($text) && (float) $text > 20000 && (float) $text < 80000) {
            return $this->sane(Carbon::create(1899, 12, 30)->addDays((int) floor((float) $text))->startOfDay());
        }

        foreach (self::DATE_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $this->sane(Carbon::instance($parsed));
            }
        }

        return null;
    }

    private function sane(Carbon $date): ?Carbon
    {
        return $date->year >= 2000 && $date->year <= 2100 ? $date : null;
    }

    /**
     * Null for a blank cell, false for something that is not a number, otherwise the number.
     * Understands "150000", "150.000", "Rp 150.000" and "79,5".
     */
    private function parseNumber(mixed $value): float|false|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $text = preg_replace('/^rp\.?\s*/i', '', $text) ?? $text;
        $text = str_replace(' ', '', $text);

        if (str_contains($text, '.') && str_contains($text, ',')) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text) === 1) {
            $text = str_replace('.', '', $text);
        }

        return is_numeric($text) ? (float) $text : false;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && $value == floor($value)) {
            $value = (string) (int) $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, bool>  $ignoredColumns
     * @return array<int, string>
     */
    private function notes(array $ignoredColumns): array
    {
        $notes = [];

        foreach (array_keys($ignoredColumns) as $field) {
            [$code, $title] = self::VALUE_COLUMNS[$field];

            $notes[] = "Kolom {$title} diabaikan karena kriteria {$code} tidak ada, tidak aktif, atau tidak diisi dengan tangan.";
        }

        return $notes;
    }
}
