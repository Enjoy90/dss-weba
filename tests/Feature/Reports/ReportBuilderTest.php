<?php

use App\Enums\OrderStatus;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Evaluation\EvaluationAdjuster;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\EvaluationWorkflow;
use App\Services\Report\CompanyProfile;
use App\Services\Report\ReportBuilder;
use App\Services\Report\ReportColumn;
use App\Services\Report\ReportFormatter;
use App\Services\Report\ReportTable;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->builder = new ReportBuilder;
});

function repOrder(int $dueInDays = 20, array $state = [], ?Customer $customer = null): ProductionOrder
{
    $state += ['customer_id' => $customer?->id ?? Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100];
    $order = ProductionOrder::factory()->dueInDays($dueInDays)->create($state);

    foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
        Assessment::factory()->create([
            'production_order_id' => $order->id,
            'criterion_id' => test()->criteria[$code]->id,
            'raw_value' => $value,
            'value_updated_at' => now(),
        ]);
    }

    return $order;
}

function repEvaluation(int $orders = 4): Evaluation
{
    foreach (range(1, $orders) as $n) {
        repOrder(2 + $n * 3);
    }

    return app(EvaluationService::class)->run(test()->manager);
}

function repTable(array $tables, string $title): ReportTable
{
    foreach ($tables as $table) {
        if ($table->title === $title) {
            return $table;
        }
    }

    throw new RuntimeException('Tabel '.$title.' tidak ada');
}

it('builds the ranking report from the saved evaluation in final rank order', function (): void {
    $evaluation = repEvaluation(4);
    $document = $this->builder->ranking($evaluation, $this->manager);
    $table = repTable($document->tables, 'Peringkat prioritas');
    $items = $evaluation->items()->get();

    expect($document->title)->toBe('Laporan Peringkat Prioritas Produksi')
        ->and($table->rows)->toHaveCount(4)
        ->and(array_column($table->rows, 0))->toBe($items->pluck('final_rank')->all())
        ->and(array_column($table->rows, 1))->toBe($items->map(fn ($item) => $item->order_snapshot['number'])->all())
        ->and(round($table->rows[0][6], 6))->toBe(round((float) $items[0]->score, 6))
        ->and($document->meta['Evaluasi'])->toBe($evaluation->name)
        ->and($document->meta['Order dihitung'])->toBe('4')
        ->and($document->printedBy)->toBe($this->manager->name);
});

it('shows a manual adjustment and its reason in the ranking report', function (): void {
    $evaluation = repEvaluation(4);
    $item = $evaluation->items()->get()->last();
    app(EvaluationAdjuster::class)->move($item, 1, 'Pelanggan kunci minta didahulukan', $this->manager);

    $table = repTable($this->builder->ranking($evaluation->fresh(), $this->manager)->tables, 'Peringkat prioritas');

    expect($table->rows[0][1])->toBe($item->order_snapshot['number'])
        ->and($table->rows[0][8])->toBe('Pelanggan kunci minta didahulukan')
        ->and($table->rows[0][7])->toBeGreaterThan(1)
        ->and($table->note)->toContain('1 order disesuaikan');
});

it('says so when the ranking has no adjustment', function (): void {
    $table = repTable($this->builder->ranking(repEvaluation(2), $this->manager)->tables, 'Peringkat prioritas');

    expect($table->note)->toContain('Tidak ada penyesuaian manual');
});

it('builds the calculation report with every step of the SAW calculation', function (): void {
    $evaluation = repEvaluation(4);
    $document = $this->builder->calculation($evaluation, $this->manager);

    expect(array_map(fn (ReportTable $table): string => $table->title, $document->tables))->toBe([
        'Kriteria dan skala',
        'Bobot dan pembagi',
        'Nilai asli',
        'Nilai skala 1 sampai 5',
        'Nilai normalisasi',
        'Nilai terbobot dan skor',
    ]);

    $raw = repTable($document->tables, 'Nilai asli');

    expect($raw->columns)->toHaveCount(2 + count($evaluation->criteria_snapshot))
        ->and($raw->rows)->toHaveCount(4);
});

it('lets the reader recalculate every score from the numbers in the calculation report', function (): void {
    $evaluation = repEvaluation(6);
    $document = $this->builder->calculation($evaluation, $this->manager);

    $divisors = repTable($document->tables, 'Bobot dan pembagi')->rows;
    $scales = repTable($document->tables, 'Nilai skala 1 sampai 5')->rows;
    $weighted = repTable($document->tables, 'Nilai terbobot dan skor')->rows;

    foreach ($scales as $index => $row) {
        $score = 0.0;

        foreach ($divisors as $column => $divisor) {
            $score += $divisor[1] * ($row[2 + $column] / $divisor[2]);
        }

        // The last column of the weighted table is the saved score.
        expect(round($score, 6))->toBe(round(end($weighted[$index]), 6));
    }
});

it('leaves the order value criterion out of the calculation report for the supervisor', function (): void {
    $evaluation = repEvaluation(3);

    $manager = $this->builder->calculation($evaluation, $this->manager);
    $supervisor = $this->builder->calculation($evaluation, $this->supervisor);

    expect(repTable($manager->tables, 'Nilai asli')->columns)->toHaveCount(2 + 7)
        ->and(repTable($supervisor->tables, 'Nilai asli')->columns)->toHaveCount(2 + 6);

    $headings = array_merge(...array_map(fn (ReportTable $table): array => $table->headings(), $supervisor->tables));
    expect($headings)->not->toContain('C6');

    foreach ($supervisor->tables as $table) {
        foreach ($table->displayRows() as $row) {
            expect(implode('|', $row))->not->toContain('Rp ')->not->toContain('Nilai order');
        }
    }
});

it('reads the letterhead from the company settings and falls back to the default name', function (): void {
    expect(CompanyProfile::load()->name)->toBe('PT Weba International')
        ->and(CompanyProfile::load()->lines())->toBe([]);

    Setting::put('company_name', 'PT Contoh Sepatu');
    Setting::put('company_address', 'Jl. Industri No. 5, Sidoarjo');
    Setting::put('company_phone', '031-555-0100');

    $profile = CompanyProfile::load();

    expect($profile->name)->toBe('PT Contoh Sepatu')
        ->and($profile->lines())->toBe(['Jl. Industri No. 5, Sidoarjo', 'Telepon 031-555-0100'])
        ->and($this->builder->lateOrders(today()->subMonth(), today(), $this->manager)->company->name)->toBe('PT Contoh Sepatu');
});

it('lists only open late orders whose deadline is inside the period', function (): void {
    $inside = repOrder(-5);
    $insideToo = repOrder(-2);
    repOrder(-40);
    repOrder(5);
    repOrder(0); // due today: still open and inside the period, but not late yet
    repOrder(-3, ['status' => OrderStatus::Done]);

    $document = $this->builder->lateOrders(today()->subDays(10), today(), $this->manager);
    $table = repTable($document->tables, 'Order terlambat');

    expect(array_column($table->rows, 0))->toBe([$inside->number, $insideToo->number])
        ->and(array_column($table->rows, 6))->toBe([5, 2])
        ->and($document->meta['Jumlah order terlambat'])->toBe('2')
        ->and($document->meta['Rata-rata keterlambatan'])->toBe('3,5 hari');
});

it('includes an order whose deadline is exactly the first or the last day of the period', function (): void {
    $first = repOrder(-6);
    $last = repOrder(-1);

    $table = repTable($this->builder->lateOrders(today()->subDays(6), today()->subDay(), $this->manager)->tables, 'Order terlambat');

    expect(array_column($table->rows, 0))->toBe([$first->number, $last->number]);
});

it('gives an empty late report a clear message instead of failing', function (): void {
    repOrder(10);

    $document = $this->builder->lateOrders(today()->subMonth(), today(), $this->manager);
    $table = repTable($document->tables, 'Order terlambat');

    expect($table->rows)->toBe([])
        ->and($table->empty)->toContain('Tidak ada order terlambat')
        ->and($document->meta['Rata-rata keterlambatan'])->toBe('-');
});

it('recaps the orders of a period per status and per customer with totals', function (): void {
    $alpha = Customer::factory()->create(['name' => 'Alfa Retail']);
    $beta = Customer::factory()->create(['name' => 'Beta Toko']);

    repOrder(10, ['order_date' => today()->subDays(2), 'quantity' => 100], $alpha);
    repOrder(11, ['order_date' => today()->subDays(2), 'quantity' => 50, 'status' => OrderStatus::Done], $alpha);
    repOrder(12, ['order_date' => today()->subDays(3), 'quantity' => 30, 'status' => OrderStatus::InProgress], $beta);
    repOrder(13, ['order_date' => today()->subDays(40), 'quantity' => 999], $beta);

    $document = $this->builder->ordersRecap(today()->subDays(5), today(), $this->manager);
    $status = repTable($document->tables, 'Rekap per status');
    $customer = repTable($document->tables, 'Rekap per pelanggan');

    expect($status->rows)->toBe([['Antri', 1, 100], ['Dikerjakan', 1, 30], ['Selesai', 1, 50], ['Dibatalkan', 0, 0]])
        ->and($status->totals)->toBe(['Total', 3, 180])
        ->and($customer->rows)->toBe([['Alfa Retail', 2, 1, 0, 1, 0, 150], ['Beta Toko', 1, 0, 1, 0, 0, 30]])
        ->and($customer->totals)->toBe(['Total', 3, 1, 1, 1, 0, 180])
        ->and($document->meta['Jumlah order'])->toBe('3');
});

it('lists the evaluations of a period with who calculated and approved them', function (): void {
    $evaluation = repEvaluation(3);
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    $document = $this->builder->evaluationHistory(today()->subDay(), today(), $this->manager);
    $table = repTable($document->tables, 'Riwayat evaluasi');

    expect($table->rows)->toHaveCount(1)
        ->and($table->rows[0][3])->toBe('Disetujui')
        ->and($table->rows[0][4])->toBe(3)
        ->and($table->rows[0][6])->toBe($this->manager->name)
        ->and($table->rows[0][7])->toBe($this->leader->name);

    $later = $this->builder->evaluationHistory(today()->addDay(), today()->addDays(3), $this->manager);
    expect(repTable($later->tables, 'Riwayat evaluasi')->rows)->toBe([]);
});

it('formats numbers and dates in the Indonesian way', function (): void {
    $table = new ReportTable('Uji', [
        ReportColumn::decimal('Skor'),
        ReportColumn::integer('Jumlah'),
        ReportColumn::date('Tanggal'),
        ReportColumn::decimal('Nilai', 4, 'rupiah'),
        ReportColumn::decimal('Mentah', 4, 'persen'),
    ], [[0.812345, 12345, Carbon::parse('2026-11-09'), 1500000.0, 'Belum diisi']]);

    expect($table->displayRows()[0])->toBe(['0,8123', '12.345', '09/11/2026', 'Rp 1.500.000', 'Belum diisi'])
        ->and(ReportFormatter::cell(ReportColumn::text('x'), null))->toBe('-');
});
