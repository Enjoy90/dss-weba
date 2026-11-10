<?php

use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Dashboard\DashboardFigures;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\HeldOrders;
use App\Services\Report\ReportBuilder;
use App\Services\Report\ReportPdf;
use App\Services\Report\ReportXlsx;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Many open orders with fresh manual values, inserted without model events so the set-up stays fast.
 */
function reportManyOrders(int $count): void
{
    test()->seed(CriteriaSeeder::class);

    $manager = User::factory()->manager()->create();
    $customers = Customer::factory()->count(5)->sequence(fn ($sequence) => ['urgency_level' => ($sequence->index % 5) + 1])->create();
    $products = Product::factory()->count(4)->create();
    $manualIds = Criterion::query()->whereIn('code', ['C3', 'C4', 'C5'])->pluck('id', 'code');
    $now = now();

    foreach (array_chunk(range(1, $count), 250) as $chunk) {
        $orders = [];

        foreach ($chunk as $number) {
            $orders[] = [
                'number' => 'REP-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'customer_id' => $customers[$number % 5]->id,
                'product_id' => $products[$number % 4]->id,
                'quantity' => 50 + ($number % 400),
                'order_date' => today()->subDays($number % 14)->toDateString(),
                'deadline_date' => today()->addDays(($number % 40) - 5)->toDateString(),
                'unit_price' => 100000 + ($number % 7) * 50000,
                'status' => $number % 6 === 0 ? 'in_progress' : 'queued',
                'priority' => 'normal',
                'lock_type' => $number % 6 === 0 ? 'auto' : 'none',
                'created_by' => $manager->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('production_orders')->insert($orders);
    }

    $assessments = [];

    foreach (ProductionOrder::query()->pluck('id') as $orderId) {
        foreach (['C3' => 7, 'C4' => 13, 'C5' => 11] as $code => $factor) {
            $assessments[] = ['production_order_id' => $orderId, 'criterion_id' => $manualIds[$code], 'raw_value' => ($orderId * $factor) % 101, 'value_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }
    }

    foreach (array_chunk($assessments, 500) as $chunk) {
        DB::table('assessments')->insert($chunk);
    }
}

it('draws the dashboard figures and the held list for 1,000 orders in under 2 seconds (NF01)', function (): void {
    reportManyOrders(1000);

    $started = microtime(true);
    $counts = app(DashboardFigures::class)->counts();
    app(HeldOrders::class)->watchList();
    $seconds = microtime(true) - $started;

    expect($counts['active'])->toBe(1000)->and($seconds)->toBeLessThan(2.0);
});

it('finds the held orders among 200 orders and five evaluations in under 2 seconds', function (): void {
    reportManyOrders(200);
    $manager = User::query()->where('role', 'manager')->firstOrFail();

    for ($i = 0; $i < 5; $i++) {
        app(EvaluationService::class)->run($manager);
    }

    $started = microtime(true);
    $held = app(HeldOrders::class)->ids();
    $seconds = microtime(true) - $started;

    expect($held)->not->toBeEmpty()->and($seconds)->toBeLessThan(2.0);
});

it('creates the calculation report of 200 orders as PDF and Excel in reasonable time', function (): void {
    reportManyOrders(200);
    $manager = User::query()->where('role', 'manager')->firstOrFail();
    $evaluation = app(EvaluationService::class)->run($manager);
    $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';

    $started = microtime(true);
    $document = (new ReportBuilder)->calculation($evaluation, $manager);
    $pdf = (new ReportPdf)->render($document);
    $pdfSeconds = microtime(true) - $started;

    $started = microtime(true);
    (new ReportXlsx)->write($document, $path);
    $xlsxSeconds = microtime(true) - $started;
    unlink($path);

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and($pdfSeconds)->toBeLessThan(30.0)
        ->and($xlsxSeconds)->toBeLessThan(5.0);
});

it('creates the ranking report of 1,000 orders as Excel in under 5 seconds', function (): void {
    reportManyOrders(1000);
    $manager = User::query()->where('role', 'manager')->firstOrFail();
    $evaluation = app(EvaluationService::class)->run($manager);
    $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';

    $started = microtime(true);
    (new ReportXlsx)->write((new ReportBuilder)->ranking($evaluation, $manager), $path);
    $seconds = microtime(true) - $started;
    unlink($path);

    expect($seconds)->toBeLessThan(5.0);
});
