<?php

use App\Enums\AssessmentSource;
use App\Enums\Completeness;
use App\Enums\OrderStatus;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Services\AssessmentResolver;
use App\Services\ResolvedValue;
use Database\Seeders\CriteriaSeeder;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->with('scales')->get()->keyBy('code');
});

function resolveFor(ProductionOrder $order, string $code, ?AssessmentResolver $resolver = null): ResolvedValue
{
    $order->load(['customer', 'assessments']);

    return ($resolver ?? AssessmentResolver::make())->resolve($order, test()->criteria[$code], $order->assessments);
}

function manualValue(ProductionOrder $order, string $code, ?float $raw, int $daysAgo = 0): Assessment
{
    return Assessment::factory()->create([
        'production_order_id' => $order->id,
        'criterion_id' => test()->criteria[$code]->id,
        'raw_value' => $raw,
        'value_updated_at' => now()->subDays($daysAgo),
    ]);
}

it('takes the remaining days to the deadline from the order itself', function (): void {
    $order = ProductionOrder::factory()->dueInDays(5)->create();

    $value = resolveFor($order, 'C1');

    expect($value->raw)->toBe(5.0)
        ->and($value->source)->toBe(AssessmentSource::Automatic)
        ->and($value->score)->toBe(3);
});

it('changes the deadline value as soon as the deadline changes, without any input', function (): void {
    $order = ProductionOrder::factory()->dueInDays(20)->create();

    expect(resolveFor($order, 'C1')->score)->toBe(1);

    $order->update(['deadline_date' => today()->addDays(1)]);

    $value = resolveFor($order->fresh(), 'C1');

    expect($value->raw)->toBe(1.0)
        ->and($value->score)->toBe(5);
});

it('gives a late order a zero or negative deadline value with the top score', function (): void {
    $late = ProductionOrder::factory()->late(4)->create();
    $today = ProductionOrder::factory()->dueInDays(0)->create();

    expect(resolveFor($late, 'C1')->raw)->toBe(-4.0)
        ->and(resolveFor($late, 'C1')->score)->toBe(5)
        ->and(resolveFor($today, 'C1')->raw)->toBe(0.0)
        ->and(resolveFor($today, 'C1')->score)->toBe(5);
});

it('shows a waiting value of ten days for an order that came in ten days ago', function (): void {
    $order = ProductionOrder::factory()->enteredDaysAgo(10)->create();

    $value = resolveFor($order, 'C7');

    expect($value->raw)->toBe(10.0)
        ->and($value->source)->toBe(AssessmentSource::Automatic)
        ->and($value->score)->toBe(5);
});

it('scores waiting from the saved scale at its boundaries', function (): void {
    $scores = collect([0, 1, 2, 3, 4, 5, 6, 7, 8])->mapWithKeys(function (int $days): array {
        $order = ProductionOrder::factory()->enteredDaysAgo($days)->create();

        return [$days => resolveFor($order, 'C7')->score];
    })->all();

    expect($scores)->toBe([0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 4, 7 => 5, 8 => 5]);
});

it('never reports a negative waiting time', function (): void {
    $order = ProductionOrder::factory()->create(['order_date' => today()->addDays(3), 'deadline_date' => today()->addDays(10)]);

    expect(resolveFor($order, 'C7')->raw)->toBe(0.0);
});

it('uses the urgency of the customer unless it is overridden for the order', function (): void {
    $order = ProductionOrder::factory()->for(Customer::factory()->urgency(2))->create();

    $value = resolveFor($order, 'C2');

    expect($value->raw)->toBe(2.0)
        ->and($value->source)->toBe(AssessmentSource::Automatic)
        ->and($value->score)->toBe(2);

    Assessment::factory()->create([
        'production_order_id' => $order->id,
        'criterion_id' => $this->criteria['C2']->id,
        'raw_value' => 5,
        'is_overridden' => true,
        'override_reason' => 'Pelanggan strategis',
    ]);

    $overridden = resolveFor($order->fresh(), 'C2');

    expect($overridden->raw)->toBe(5.0)
        ->and($overridden->source)->toBe(AssessmentSource::Override)
        ->and($overridden->overrideReason)->toBe('Pelanggan strategis')
        ->and($overridden->score)->toBe(5);
});

it('follows a change of the customer urgency for orders that are not overridden', function (): void {
    $customer = Customer::factory()->urgency(2)->create();
    $order = ProductionOrder::factory()->for($customer)->create();

    $customer->update(['urgency_level' => 5]);

    expect(resolveFor($order->fresh(), 'C2')->raw)->toBe(5.0);
});

it('ignores an assessment row that is not marked as an override for urgency', function (): void {
    $order = ProductionOrder::factory()->for(Customer::factory()->urgency(3))->create();

    Assessment::factory()->create([
        'production_order_id' => $order->id,
        'criterion_id' => $this->criteria['C2']->id,
        'raw_value' => 1,
        'is_overridden' => false,
    ]);

    expect(resolveFor($order->fresh(), 'C2')->raw)->toBe(3.0);
});

it('multiplies quantity by unit price for the order value and falls back to manual without a price', function (): void {
    $priced = ProductionOrder::factory()->create(['quantity' => 200, 'unit_price' => 150000]);
    $unpriced = ProductionOrder::factory()->create(['quantity' => 200, 'unit_price' => null]);

    $auto = resolveFor($priced, 'C6');

    expect($auto->raw)->toBe(30000000.0)
        ->and($auto->source)->toBe(AssessmentSource::Automatic)
        ->and($auto->score)->toBe(3);

    $empty = resolveFor($unpriced, 'C6');

    expect($empty->source)->toBe(AssessmentSource::Empty)
        ->and($empty->isMissing())->toBeTrue();

    manualValue($unpriced, 'C6', 120000000);

    $manual = resolveFor($unpriced->fresh(), 'C6');

    expect($manual->source)->toBe(AssessmentSource::Manual)
        ->and($manual->raw)->toBe(120000000.0)
        ->and($manual->score)->toBe(5);
});

it('treats a missing manual value as the worst score and flags it', function (): void {
    $order = ProductionOrder::factory()->create();

    $value = resolveFor($order, 'C3');

    expect($value->raw)->toBeNull()
        ->and($value->source)->toBe(AssessmentSource::Empty)
        ->and($value->score)->toBe(1)
        ->and($value->isMissing())->toBeTrue()
        ->and($value->stale)->toBeFalse();
});

it('treats a row with an empty value the same as no row at all', function (): void {
    $order = ProductionOrder::factory()->create();
    manualValue($order, 'C3', null);

    expect(resolveFor($order->fresh(), 'C3')->source)->toBe(AssessmentSource::Empty);
});

it('converts a manual value with the saved scale', function (): void {
    $order = ProductionOrder::factory()->create();
    manualValue($order, 'C3', 79.5);
    manualValue($order, 'C5', 80);

    $order = $order->fresh();

    expect(resolveFor($order, 'C3')->score)->toBe(4)
        ->and(resolveFor($order, 'C5')->score)->toBe(5);
});

it('marks a manual value stale only when it is older than the limit in calendar days', function (): void {
    $order = ProductionOrder::factory()->create();
    manualValue($order, 'C3', 80, 3);
    manualValue($order, 'C4', 20, 4);

    $order = $order->fresh();

    expect(resolveFor($order, 'C3')->stale)->toBeFalse()
        ->and(resolveFor($order, 'C4')->stale)->toBeTrue();
});

it('follows the stale limit from the settings', function (): void {
    $order = ProductionOrder::factory()->create();
    manualValue($order, 'C3', 80, 4);

    Setting::put('stale_value_days', 7);

    expect(resolveFor($order->fresh(), 'C3')->stale)->toBeFalse();

    Setting::put('stale_value_days', 1);

    expect(resolveFor($order->fresh(), 'C3')->stale)->toBeTrue();
});

it('does not mark automatic values or an urgency override as stale', function (): void {
    $order = ProductionOrder::factory()->enteredDaysAgo(30)->create();

    Assessment::factory()->create([
        'production_order_id' => $order->id,
        'criterion_id' => $this->criteria['C2']->id,
        'raw_value' => 4,
        'is_overridden' => true,
        'override_reason' => 'Contoh',
        'value_updated_at' => now()->subDays(40),
    ]);

    $order = $order->fresh();

    expect(resolveFor($order, 'C7')->stale)->toBeFalse()
        ->and(resolveFor($order, 'C2')->stale)->toBeFalse();
});

it('judges completeness with incomplete before stale before complete', function (): void {
    $resolver = AssessmentResolver::make();
    $criteria = $this->criteria->filter(fn (Criterion $criterion): bool => in_array($criterion->code, ['C3', 'C4', 'C5'], true))->values();

    $complete = ProductionOrder::factory()->create();
    foreach (['C3', 'C4', 'C5'] as $code) {
        manualValue($complete, $code, 50, 0);
    }

    $stale = ProductionOrder::factory()->create();
    foreach (['C3', 'C4', 'C5'] as $code) {
        manualValue($stale, $code, 50, $code === 'C4' ? 6 : 0);
    }

    $incomplete = ProductionOrder::factory()->create();
    manualValue($incomplete, 'C3', 50, 9);
    manualValue($incomplete, 'C5', 50, 0);

    $check = fn (ProductionOrder $order): Completeness => $resolver->completeness(
        $resolver->resolveAll($order->fresh(['customer', 'assessments']), $criteria),
    );

    expect($check($complete))->toBe(Completeness::Complete)
        ->and($check($stale))->toBe(Completeness::Stale)
        ->and($check($incomplete))->toBe(Completeness::Incomplete);
});

it('finds the open orders in a completeness state and leaves finished ones out', function (): void {
    $resolver = AssessmentResolver::make();
    $criteria = $this->criteria->filter(fn (Criterion $criterion): bool => $criterion->code === 'C3')->values();

    $open = ProductionOrder::factory()->create();
    $done = ProductionOrder::factory()->status(OrderStatus::Done)->create();
    $canceled = ProductionOrder::factory()->status(OrderStatus::Canceled)->create();
    $filled = ProductionOrder::factory()->create();
    manualValue($filled, 'C3', 70);

    $incomplete = $resolver->orderIdsWith(Completeness::Incomplete, $criteria);
    $complete = $resolver->orderIdsWith(Completeness::Complete, $criteria);

    expect($incomplete)->toBe([$open->id])
        ->and($complete)->toBe([$filled->id])
        ->and($incomplete)->not->toContain($done->id, $canceled->id);
});

it('resolves with a fixed date when one is given', function (): void {
    $order = ProductionOrder::factory()->dueInDays(10)->create();
    $order->load(['customer', 'assessments']);

    $later = AssessmentResolver::make(today()->addDays(9));

    expect($later->resolve($order, $this->criteria['C1'], $order->assessments)->raw)->toBe(1.0);
});
