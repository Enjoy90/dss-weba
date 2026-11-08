<?php

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
use App\Enums\LockType;
use App\Enums\OrderStatus;
use App\Enums\RankGroup;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Evaluation\EvaluationBlockedException;
use App\Services\Evaluation\EvaluationNeedsConfirmationException;
use App\Services\Evaluation\EvaluationService;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();
});

/**
 * An order with every manual value filled in and fresh, so nothing is incomplete or stale.
 */
function evalOrder(?ProductionOrder $order = null, float $material = 100, float $hours = 10, float $progress = 50, int $valueAgeDays = 0): ProductionOrder
{
    $order ??= ProductionOrder::factory()->create();

    foreach (['C3' => $material, 'C4' => $hours, 'C5' => $progress] as $code => $value) {
        Assessment::factory()->create([
            'production_order_id' => $order->id,
            'criterion_id' => test()->criteria[$code]->id,
            'raw_value' => $value,
            'value_updated_at' => now()->subDays($valueAgeDays),
        ]);
    }

    return $order;
}

function evalRun(array $arguments = []): Evaluation
{
    return app(EvaluationService::class)->run(test()->manager, ...$arguments + ['acknowledgeWarnings' => true]);
}

function evalItem(Evaluation $evaluation, ProductionOrder $order): EvaluationItem
{
    return $evaluation->items()->where('production_order_id', $order->id)->firstOrFail();
}

it('refuses to start when there is no open order', function (): void {
    ProductionOrder::factory()->status(OrderStatus::Done)->create();

    $preflight = app(EvaluationService::class)->preflight();

    expect($preflight->canRun())->toBeFalse()
        ->and($preflight->blockers[0])->toContain('Belum ada order');

    expect(fn () => evalRun())->toThrow(EvaluationBlockedException::class);
    expect(Evaluation::query()->count())->toBe(0);
});

it('refuses to start when the weights do not total 100 percent', function (): void {
    evalOrder();
    $this->criteria['C1']->update(['weight' => 0.5]);

    $preflight = app(EvaluationService::class)->preflight();

    expect($preflight->canRun())->toBeFalse()
        ->and($preflight->blockers[0])->toContain('total bobot harus tepat 100%');
});

it('refuses to start when a scale is incomplete', function (): void {
    evalOrder();
    $this->criteria['C3']->scales()->where('score', 3)->delete();

    $preflight = app(EvaluationService::class)->preflight();

    expect($preflight->canRun())->toBeFalse()
        ->and($preflight->blockers[0])->toContain('C3');

    expect(fn () => evalRun())->toThrow(EvaluationBlockedException::class);
});

it('lists incomplete and stale orders and counts an order in one list only', function (): void {
    $complete = evalOrder();
    $incomplete = ProductionOrder::factory()->create();
    evalOrder($incomplete);
    Assessment::query()->where('production_order_id', $incomplete->id)->where('criterion_id', $this->criteria['C4']->id)->update(['raw_value' => null]);
    $stale = evalOrder(valueAgeDays: 5);
    $both = ProductionOrder::factory()->create();
    evalOrder($both, valueAgeDays: 5);
    Assessment::query()->where('production_order_id', $both->id)->where('criterion_id', $this->criteria['C5']->id)->update(['raw_value' => null]);

    $preflight = app(EvaluationService::class)->preflight();

    expect($preflight->canRun())->toBeTrue()
        ->and($preflight->orderCount())->toBe(4)
        ->and(collect($preflight->incomplete)->pluck('number')->all())->toEqualCanonicalizing([$incomplete->number, $both->number])
        ->and(collect($preflight->incomplete)->firstWhere('order_id', $incomplete->id)['criteria'])->toBe(['C4'])
        ->and(collect($preflight->stale)->pluck('number')->all())->toBe([$stale->number])
        ->and(collect($preflight->stale)->firstWhere('order_id', $stale->id)['criteria'])->toBe(['C3', 'C4', 'C5'])
        ->and($preflight->hasWarnings())->toBeTrue();

    expect(collect($preflight->incomplete)->pluck('order_id')->all())->not->toContain($complete->id);
});

it('asks for confirmation when orders are incomplete and saves nothing until it is given', function (): void {
    evalOrder();
    ProductionOrder::factory()->create();

    expect(fn () => app(EvaluationService::class)->run($this->manager))
        ->toThrow(EvaluationNeedsConfirmationException::class);

    expect(Evaluation::query()->count())->toBe(0);

    $evaluation = app(EvaluationService::class)->run($this->manager, acknowledgeWarnings: true);

    expect($evaluation->order_count)->toBe(2);
});

it('needs no confirmation when everything is complete and fresh', function (): void {
    evalOrder();
    evalOrder();

    $evaluation = app(EvaluationService::class)->run($this->manager);

    expect($evaluation->exists)->toBeTrue()
        ->and($evaluation->order_count)->toBe(2);
});

it('saves an evaluation with one item per open order and nothing for finished ones', function (): void {
    $queued = evalOrder();
    $working = evalOrder(ProductionOrder::factory()->inProgress()->create());
    ProductionOrder::factory()->status(OrderStatus::Done)->create();
    ProductionOrder::factory()->status(OrderStatus::Canceled)->create();
    $deleted = ProductionOrder::factory()->create();
    $deleted->delete();

    $evaluation = evalRun(['name' => '  Evaluasi pagi  ', 'notes' => 'Catatan uji']);

    expect($evaluation->name)->toBe('Evaluasi pagi')
        ->and($evaluation->notes)->toBe('Catatan uji')
        ->and($evaluation->status)->toBe(EvaluationStatus::Draft)
        ->and($evaluation->trigger)->toBe(EvaluationTrigger::Manual)
        ->and($evaluation->evaluated_by)->toBe($this->manager->id)
        ->and($evaluation->order_count)->toBe(2)
        ->and($evaluation->top_n)->toBe(5)
        ->and($evaluation->items()->pluck('production_order_id')->all())->toEqualCanonicalizing([$queued->id, $working->id]);
});

it('names the evaluation after the date and time when no name is given', function (): void {
    evalOrder();
    $this->travelTo(now()->setDate(2026, 11, 9)->setTime(6, 5));

    $evaluation = evalRun();

    expect($evaluation->name)->toBe('Evaluasi 09/11/2026 06:05')
        ->and($evaluation->notes)->toBeNull();
});

it('stores the raw value, scale, normalized value, weighted value and score of each order', function (): void {
    $order = evalOrder(ProductionOrder::factory()->dueInDays(5)->enteredDaysAgo(2)->create(), material: 80, hours: 30, progress: 45);
    evalOrder(ProductionOrder::factory()->dueInDays(1)->enteredDaysAgo(0)->create(), material: 100, hours: 5, progress: 90);

    $evaluation = evalRun();
    $item = evalItem($evaluation, $order);

    expect($item->raw_values['C1'])->toEqual(5)
        ->and($item->raw_values['C3'])->toEqual(80)
        ->and($item->raw_values['C7'])->toEqual(2)
        ->and($item->scale_values['C1'])->toBe(3)
        ->and($item->scale_values['C3'])->toBe(4)
        ->and($item->scale_values['C4'])->toBe(3)
        ->and($item->scale_values['C5'])->toBe(3)
        ->and($item->scale_values['C7'])->toBe(2)
        ->and($item->waiting_days)->toBe(2);

    // Normalized value is the scale divided by the largest scale in that column.
    $max = EvaluationItem::query()->where('evaluation_id', $evaluation->id)->get()->max(fn ($row) => $row->scale_values['C1']);
    expect($item->normalized_values['C1'])->toBe(round(3 / $max, 6));

    $weights = collect($evaluation->criteria_snapshot)->pluck('weight', 'code');

    foreach ($item->weighted_values as $code => $weighted) {
        expect($weighted)->toBe(round($weights[$code] * $item->normalized_values[$code], 6));
    }

    expect((float) $item->score)->toBe(round(array_sum($item->weighted_values), 6));
});

it('ranks the system order by score and keeps every rank unique', function (): void {
    foreach ([30, 3, 12, 20, 6] as $days) {
        evalOrder(ProductionOrder::factory()->dueInDays($days)->create());
    }

    $items = evalRun()->items()->get();

    expect($items->pluck('final_rank')->sort()->values()->all())->toBe([1, 2, 3, 4, 5])
        ->and($items->pluck('system_rank')->sort()->values()->all())->toBe([1, 2, 3, 4, 5]);

    $bySystem = $items->sortBy('system_rank')->pluck('score')->map(fn ($score) => (float) $score)->all();
    $sorted = $bySystem;
    rsort($sorted);

    expect($bySystem)->toBe($sorted);
});

it('keeps a copy of the criteria, weights, scales and settings that does not follow later changes', function (): void {
    evalOrder();
    Setting::put('max_wait_days', 9);

    $evaluation = evalRun();

    $this->criteria['C1']->update(['name' => 'Nama baru']);
    $this->criteria['C1']->scales()->where('score', 5)->update(['upper_bound' => 99]);

    $fresh = Evaluation::query()->findOrFail($evaluation->id);
    $c1 = collect($fresh->criteria_snapshot)->firstWhere('code', 'C1');

    expect($fresh->criteria_snapshot)->toHaveCount(7)
        ->and(collect($fresh->criteria_snapshot)->sum('weight'))->toEqualWithDelta(1.0, 0.000001)
        ->and($c1['name'])->toBe('Sisa waktu menuju tenggat')
        ->and($c1['weight'])->toBe(0.27)
        ->and($c1['scales'])->toHaveCount(5)
        ->and(collect($c1['scales'])->firstWhere('score', 5)['upper_bound'])->toEqual(2)
        ->and($fresh->settings_snapshot)->toMatchArray([
            'weight_mode' => 'direct',
            'stale_value_days' => 3,
            'lock_in_progress' => true,
            'max_wait_days' => 9,
            'top_n_priority' => 5,
            'evaluation_date' => today()->toDateString(),
        ]);
});

it('leaves prices out of the order copy', function (): void {
    $order = evalOrder(ProductionOrder::factory()->create(['unit_price' => 123456, 'quantity' => 10]));

    $snapshot = evalItem(evalRun(), $order)->order_snapshot;

    expect($snapshot)->toHaveKeys(['number', 'customer', 'product', 'quantity', 'order_date', 'deadline_date', 'status', 'lock_type', 'lock_until'])
        ->and(json_encode($snapshot))->not->toContain('123456')
        ->and($snapshot['number'])->toBe($order->number);
});

it('records missing and stale values as warnings and scores a missing value as 1', function (): void {
    $order = evalOrder(valueAgeDays: 5);
    Assessment::query()->where('production_order_id', $order->id)->where('criterion_id', $this->criteria['C3']->id)->update(['raw_value' => null]);

    $item = evalItem(evalRun(), $order);

    expect($item->warnings)->toBe([
        ['criterion' => 'C3', 'type' => 'empty'],
        ['criterion' => 'C4', 'type' => 'stale'],
        ['criterion' => 'C5', 'type' => 'stale'],
    ])
        ->and($item->raw_values['C3'])->toBeNull()
        ->and($item->scale_values['C3'])->toBe(1);
});

it('puts an order being worked on above higher scoring queued orders', function (): void {
    $urgent = evalOrder(ProductionOrder::factory()->dueInDays(0)->create(), material: 100, hours: 2, progress: 95);
    $working = evalOrder(ProductionOrder::factory()->inProgress()->dueInDays(25)->create(), material: 10, hours: 200, progress: 5);

    $evaluation = evalRun();
    $workingItem = evalItem($evaluation, $working);
    $urgentItem = evalItem($evaluation, $urgent);

    expect((float) $urgentItem->score)->toBeGreaterThan((float) $workingItem->score)
        ->and($urgentItem->system_rank)->toBe(1)
        ->and($workingItem->system_rank)->toBe(2)
        ->and($workingItem->rank_group)->toBe(RankGroup::Locked)
        ->and($workingItem->final_rank)->toBe(1)
        ->and($urgentItem->final_rank)->toBe(2)
        ->and($urgentItem->rank_group)->toBe(RankGroup::Regular);
});

it('escalates an order waiting 8 days with a limit of 7 but not one waiting exactly 7', function (): void {
    $strong = evalOrder(ProductionOrder::factory()->dueInDays(0)->enteredDaysAgo(1)->create(), material: 100, hours: 2, progress: 95);
    $seven = evalOrder(ProductionOrder::factory()->dueInDays(25)->enteredDaysAgo(7)->create(), material: 10, hours: 200, progress: 5);
    $eight = evalOrder(ProductionOrder::factory()->dueInDays(25)->enteredDaysAgo(8)->create(), material: 10, hours: 200, progress: 5);

    $evaluation = evalRun();

    expect(evalItem($evaluation, $eight)->rank_group)->toBe(RankGroup::Escalated)
        ->and(evalItem($evaluation, $seven)->rank_group)->toBe(RankGroup::Regular)
        ->and(evalItem($evaluation, $strong)->rank_group)->toBe(RankGroup::Regular)
        ->and(evalItem($evaluation, $eight)->waiting_days)->toBe(8)
        ->and(evalItem($evaluation, $eight)->final_rank)->toBe(1)
        ->and(evalItem($evaluation, $eight)->system_rank)->toBeGreaterThan(1)
        ->and(evalItem($evaluation, $strong)->system_rank)->toBe(1)
        ->and(evalItem($evaluation, $strong)->final_rank)->toBe(2);
});

it('follows the maximum waiting days from the settings', function (): void {
    evalOrder(ProductionOrder::factory()->dueInDays(0)->create(), material: 100, hours: 2, progress: 95);
    $five = evalOrder(ProductionOrder::factory()->dueInDays(25)->enteredDaysAgo(6)->create(), material: 10, hours: 200, progress: 5);

    expect(evalItem(evalRun(), $five)->rank_group)->toBe(RankGroup::Regular);

    Setting::put('max_wait_days', 5);

    expect(evalItem(evalRun(), $five)->rank_group)->toBe(RankGroup::Escalated);
});

it('does not escalate an order that is locked or already being worked on', function (): void {
    $manualLock = evalOrder(ProductionOrder::factory()->enteredDaysAgo(20)->create([
        'lock_type' => LockType::Manual, 'lock_reason' => 'Pelanggan penting', 'lock_until' => null,
    ]));
    $working = evalOrder(ProductionOrder::factory()->inProgress()->enteredDaysAgo(20)->create());

    $evaluation = evalRun();

    expect(evalItem($evaluation, $manualLock)->rank_group)->toBe(RankGroup::Locked)
        ->and(evalItem($evaluation, $working)->rank_group)->toBe(RankGroup::Locked);
});

it('lets a manual lock hold through its end date and lapse the day after', function (): void {
    $through = evalOrder(ProductionOrder::factory()->create([
        'lock_type' => LockType::Manual, 'lock_reason' => 'Sampai hari ini', 'lock_until' => today(),
    ]));
    $lapsed = evalOrder(ProductionOrder::factory()->enteredDaysAgo(3)->create([
        'lock_type' => LockType::Manual, 'lock_reason' => 'Sudah lewat', 'lock_until' => today()->subDay(),
    ]));
    $open = evalOrder(ProductionOrder::factory()->create([
        'lock_type' => LockType::Manual, 'lock_reason' => 'Tanpa batas', 'lock_until' => today()->addDays(10),
    ]));

    $evaluation = evalRun();

    expect(evalItem($evaluation, $through)->rank_group)->toBe(RankGroup::Locked)
        ->and(evalItem($evaluation, $open)->rank_group)->toBe(RankGroup::Locked)
        ->and(evalItem($evaluation, $lapsed)->rank_group)->toBe(RankGroup::Regular);
});

it('sends a lapsed lock to escalation when the order has waited too long', function (): void {
    $lapsed = evalOrder(ProductionOrder::factory()->enteredDaysAgo(9)->create([
        'lock_type' => LockType::Manual, 'lock_reason' => 'Sudah lewat', 'lock_until' => today()->subDay(),
    ]));
    evalOrder();

    expect(evalItem(evalRun(), $lapsed)->rank_group)->toBe(RankGroup::Escalated);
});

it('ignores the automatic lock when the setting is switched off', function (): void {
    $working = evalOrder(ProductionOrder::factory()->inProgress()->enteredDaysAgo(30)->create());
    $manual = evalOrder(ProductionOrder::factory()->create(['lock_type' => LockType::Manual, 'lock_reason' => 'Tetap', 'lock_until' => null]));

    Setting::put('lock_in_progress', false);

    $evaluation = evalRun();

    expect(evalItem($evaluation, $working)->rank_group)->toBe(RankGroup::Regular)
        ->and(evalItem($evaluation, $manual)->rank_group)->toBe(RankGroup::Locked)
        ->and($evaluation->settings_snapshot['lock_in_progress'])->toBeFalse();
});

it('reads the previous final rank from the latest evaluation that was not canceled', function (): void {
    $first = evalOrder(ProductionOrder::factory()->dueInDays(2)->create());
    $second = evalOrder(ProductionOrder::factory()->dueInDays(20)->create());

    $one = evalRun();
    expect(evalItem($one, $first)->previous_rank)->toBeNull();

    $newcomer = evalOrder(ProductionOrder::factory()->dueInDays(0)->create(), material: 100, hours: 2, progress: 95);

    $two = evalRun();

    expect(evalItem($two, $first)->previous_rank)->toBe(evalItem($one, $first)->final_rank)
        ->and(evalItem($two, $second)->previous_rank)->toBe(evalItem($one, $second)->final_rank)
        ->and(evalItem($two, $newcomer)->previous_rank)->toBeNull()
        ->and(evalItem($two, $newcomer)->rankChange())->toBeNull()
        ->and(evalItem($two, $first)->rankChange())->toBe(evalItem($one, $first)->final_rank - evalItem($two, $first)->final_rank);

    $two->update(['status' => EvaluationStatus::Canceled]);

    $three = evalRun();

    expect(evalItem($three, $first)->previous_rank)->toBe(evalItem($one, $first)->final_rank)
        ->and(evalItem($three, $newcomer)->previous_rank)->toBeNull();
});

it('writes one activity log row for each calculation', function (): void {
    evalOrder();

    $evaluation = evalRun(['name' => 'Uji log']);

    $log = ActivityLog::query()->where('action', 'evaluation.created')->sole();

    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->subject_id)->toBe($evaluation->id)
        ->and($log->new_values)->toBe(['name' => 'Uji log', 'trigger' => 'manual', 'order_count' => 1])
        ->and($log->subject)->toBeInstanceOf(Evaluation::class);
});

it('can be run by the system for a scheduled evaluation without a user', function (): void {
    evalOrder();

    $evaluation = app(EvaluationService::class)->run(null, EvaluationTrigger::Scheduled);

    expect($evaluation->evaluated_by)->toBeNull()
        ->and($evaluation->trigger)->toBe(EvaluationTrigger::Scheduled);
});

it('saves everything or nothing', function (): void {
    evalOrder();
    evalOrder();

    ActivityLog::creating(function (): void {
        throw new RuntimeException('Gagal menulis log.');
    });

    expect(fn () => evalRun())->toThrow(RuntimeException::class);

    expect(Evaluation::query()->count())->toBe(0)
        ->and(EvaluationItem::query()->count())->toBe(0);
});

it('keeps the evaluation time when the status changes', function (): void {
    evalOrder();
    $this->travelTo(now()->setTime(6, 0));

    $evaluation = evalRun();
    $stamp = $evaluation->evaluated_at->toDateTimeString();

    $this->travelTo(now()->addDays(2));
    $evaluation->update(['status' => EvaluationStatus::Submitted, 'submitted_by' => $this->manager->id, 'submitted_at' => now()]);

    expect(Evaluation::query()->find($evaluation->id)->evaluated_at->toDateTimeString())->toBe($stamp);
});

it('has no automatic update clause on the evaluation time column', function (): void {
    $extra = DB::table('information_schema.columns')
        ->where('table_schema', DB::getDatabaseName())
        ->where('table_name', 'evaluations')
        ->where('column_name', 'evaluated_at')
        ->value('EXTRA');

    expect(strtolower((string) $extra))->not->toContain('on update');
});

it('refuses to change or delete a saved evaluation apart from its status', function (): void {
    $order = evalOrder();
    $evaluation = evalRun();
    $item = evalItem($evaluation, $order);

    expect(fn () => $evaluation->update(['name' => 'Nama lain']))->toThrow(LogicException::class)
        ->and(fn () => $evaluation->update(['evaluated_at' => now()->addDay()]))->toThrow(LogicException::class)
        ->and(fn () => $evaluation->update(['order_count' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $evaluation->update(['criteria_snapshot' => []]))->toThrow(LogicException::class)
        ->and(fn () => $evaluation->delete())->toThrow(LogicException::class)
        ->and(fn () => $item->update(['score' => 0.1]))->toThrow(LogicException::class)
        ->and(fn () => $item->update(['system_rank' => 9]))->toThrow(LogicException::class)
        ->and(fn () => $item->delete())->toThrow(LogicException::class);

    // A refused update leaves the model dirty, so the allowed changes go through fresh copies.
    $evaluation->fresh()->update(['status' => EvaluationStatus::Canceled]);
    $item->fresh()->update(['final_rank' => 3, 'adjustment_reason' => 'Permintaan pimpinan']);

    expect(Evaluation::query()->find($evaluation->id)->status)->toBe(EvaluationStatus::Canceled)
        ->and(EvaluationItem::query()->find($item->id)->final_rank)->toBe(3)
        ->and(EvaluationItem::query()->find($item->id)->system_rank)->toBe(1);
});
