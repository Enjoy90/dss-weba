<?php

use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills the database with many open orders, all with fresh manual values, without model events
 * so the set-up itself stays fast. Deadlines, entry dates and values are spread out on purpose.
 */
function seedManyOrders(int $count): void
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
                'number' => 'PERF-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
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
        $assessments[] = ['production_order_id' => $orderId, 'criterion_id' => $manualIds['C3'], 'raw_value' => ($orderId * 7) % 101, 'value_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        $assessments[] = ['production_order_id' => $orderId, 'criterion_id' => $manualIds['C4'], 'raw_value' => ($orderId * 13) % 120, 'value_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        $assessments[] = ['production_order_id' => $orderId, 'criterion_id' => $manualIds['C5'], 'raw_value' => ($orderId * 11) % 101, 'value_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now];
    }

    foreach (array_chunk($assessments, 500) as $chunk) {
        DB::table('assessments')->insert($chunk);
    }
}

it('calculates 200 orders in under 5 seconds (NF01)', function (): void {
    seedManyOrders(200);
    $manager = User::query()->where('role', 'manager')->firstOrFail();

    $started = microtime(true);
    $evaluation = app(EvaluationService::class)->run($manager);
    $seconds = microtime(true) - $started;

    expect($evaluation->order_count)->toBe(200)
        ->and($evaluation->items()->count())->toBe(200)
        ->and($seconds)->toBeLessThan(5.0);
});

it('calculates 1,000 orders in a reasonable time and ranks every one of them', function (): void {
    seedManyOrders(1000);
    $manager = User::query()->where('role', 'manager')->firstOrFail();

    $started = microtime(true);
    $evaluation = app(EvaluationService::class)->run($manager);
    $seconds = microtime(true) - $started;

    $ranks = $evaluation->items()->pluck('final_rank')->sort()->values();

    expect($evaluation->order_count)->toBe(1000)
        ->and($ranks->first())->toBe(1)
        ->and($ranks->last())->toBe(1000)
        ->and($ranks->unique()->count())->toBe(1000)
        ->and(Evaluation::query()->count())->toBe(1)
        ->and($seconds)->toBeLessThan(20.0);
});
