<?php

use App\Enums\EvaluationStatus;
use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\RankGroup;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Evaluation\EvaluationAdjuster;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\EvaluationWorkflow;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->admin = User::factory()->admin()->create();
});

/**
 * An open order with fresh manual values. The deadline decides roughly where it ranks: nearer ranks higher.
 */
function flowOrder(int $dueInDays = 20, array $state = [], ?OrderStatus $status = null): ProductionOrder
{
    // Price, quantity and customer urgency are fixed so that only the deadline and the state decide the ranking.
    $state += ['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100];
    $factory = ProductionOrder::factory()->dueInDays($dueInDays);
    $order = $status === OrderStatus::InProgress ? $factory->inProgress()->create($state) : $factory->create($state);

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

function flowEvaluate(): Evaluation
{
    return app(EvaluationService::class)->run(test()->manager);
}

function flowItem(Evaluation $evaluation, ProductionOrder $order): EvaluationItem
{
    return $evaluation->items()->where('production_order_id', $order->id)->firstOrFail();
}

function flowInvalid(callable $call, string $field): void
{
    try {
        $call();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    throw new RuntimeException('Diharapkan ValidationException pada '.$field);
}

function flowApproved(): Evaluation
{
    $evaluation = flowEvaluate();
    app(EvaluationWorkflow::class)->submit($evaluation, test()->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, test()->leader);

    return $evaluation->fresh();
}

it('lets the manager submit a draft and logs it', function (): void {
    flowOrder();
    $evaluation = flowEvaluate();

    $submitted = app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    expect($submitted->status)->toBe(EvaluationStatus::Submitted)
        ->and($submitted->submitted_by)->toBe($this->manager->id)
        ->and($submitted->submitted_at)->not->toBeNull();

    $log = ActivityLog::query()->where('action', 'evaluation.submitted')->sole();

    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->old_values)->toBe(['status' => 'draft'])
        ->and($log->new_values['status'])->toBe('submitted');
});

it('lets only the manager submit', function (): void {
    flowOrder();
    $evaluation = flowEvaluate();

    foreach ([$this->leader, $this->supervisor, $this->admin] as $user) {
        expect(fn () => app(EvaluationWorkflow::class)->submit($evaluation, $user))->toThrow(AuthorizationException::class);
    }

    expect($evaluation->fresh()->status)->toBe(EvaluationStatus::Draft);
});

it('refuses to submit when approval is not required or when the status is wrong', function (): void {
    flowOrder();
    $evaluation = flowEvaluate();

    Setting::put('approval_required', false);
    flowInvalid(fn () => app(EvaluationWorkflow::class)->submit($evaluation, $this->manager), 'evaluation');

    Setting::put('approval_required', true);
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    flowInvalid(fn () => app(EvaluationWorkflow::class)->submit($evaluation, $this->manager), 'evaluation');
});

it('lets only the leader approve, and only a submitted evaluation', function (): void {
    flowOrder();
    $evaluation = flowEvaluate();

    flowInvalid(fn () => app(EvaluationWorkflow::class)->approve($evaluation, $this->leader), 'evaluation');

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    foreach ([$this->manager, $this->supervisor, $this->admin] as $user) {
        expect(fn () => app(EvaluationWorkflow::class)->approve($evaluation, $user))->toThrow(AuthorizationException::class);
    }

    $approved = app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    expect($approved->status)->toBe(EvaluationStatus::Approved)
        ->and($approved->approved_by)->toBe($this->leader->id)
        ->and($approved->approved_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', 'evaluation.approved')->count())->toBe(1);
});

it('sends a rejected evaluation back to draft with the note and clears it on the next submit', function (): void {
    flowOrder();
    $evaluation = flowEvaluate();
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    flowInvalid(fn () => app(EvaluationWorkflow::class)->reject($evaluation, $this->leader, '  '), 'note');
    flowInvalid(fn () => app(EvaluationWorkflow::class)->reject($evaluation, $this->leader, str_repeat('a', 501)), 'note');
    expect(fn () => app(EvaluationWorkflow::class)->reject($evaluation, $this->manager, 'Tidak sesuai'))->toThrow(AuthorizationException::class);

    $rejected = app(EvaluationWorkflow::class)->reject($evaluation, $this->leader, ' Urutan pelanggan A belum pas ');

    expect($rejected->status)->toBe(EvaluationStatus::Draft)
        ->and($rejected->rejection_note)->toBe('Urutan pelanggan A belum pas')
        ->and($rejected->submitted_by)->toBeNull()
        ->and($rejected->submitted_at)->toBeNull();

    $log = ActivityLog::query()->where('action', 'evaluation.rejected')->sole();
    expect($log->new_values['rejection_note'])->toBe('Urutan pelanggan A belum pas');

    $again = app(EvaluationWorkflow::class)->submit($rejected, $this->manager);
    expect($again->status)->toBe(EvaluationStatus::Submitted)->and($again->rejection_note)->toBeNull();
});

it('applies the first N queued orders as Prioritas Utama and puts every other order back to Normal', function (): void {
    Setting::put('top_n_priority', 2);

    $orders = collect([3, 6, 10, 15])->map(fn (int $days) => flowOrder($days));
    $stale = flowOrder(25, ['priority' => OrderPriority::Top]);

    $evaluation = flowApprovedFor();
    $result = app(EvaluationWorkflow::class)->apply($evaluation, $this->manager);

    $top = ProductionOrder::query()->where('priority', 'top')->pluck('id')->all();
    $expected = $evaluation->items()->orderBy('final_rank')->limit(2)->pluck('production_order_id')->all();

    expect($result->status)->toBe(EvaluationStatus::Applied)
        ->and($result->applied_by)->toBe($this->manager->id)
        ->and($result->applied_at)->not->toBeNull()
        ->and($top)->toEqualCanonicalizing($expected)
        ->and($stale->fresh()->priority)->toBe(OrderPriority::Normal)
        ->and(ActivityLog::query()->where('action', 'evaluation.applied')->count())->toBe(1);

    expect($orders->count())->toBe(4);
});

function flowApprovedFor(): Evaluation
{
    return flowApproved();
}

it('uses the N saved in the evaluation, not the setting of today', function (): void {
    Setting::put('top_n_priority', 3);
    foreach ([2, 4, 8, 12, 16] as $days) {
        flowOrder($days);
    }

    $evaluation = flowApproved();
    Setting::put('top_n_priority', 1);

    app(EvaluationWorkflow::class)->apply($evaluation, $this->leader);

    expect(ProductionOrder::query()->where('priority', 'top')->count())->toBe(3);
});

it('still labels five queued orders when six are being worked on (PRD F18 acceptance 5)', function (): void {
    foreach (range(1, 6) as $i) {
        flowOrder(30, [], OrderStatus::InProgress);
    }

    $queued = collect([2, 4, 6, 8, 10, 12, 14])->map(fn (int $days) => flowOrder($days));

    app(EvaluationWorkflow::class)->apply(flowApproved(), $this->manager);

    $labelled = ProductionOrder::query()->where('priority', 'top')->get();

    expect($labelled)->toHaveCount(5)
        ->and($labelled->every(fn (ProductionOrder $order) => $order->status === OrderStatus::Queued))->toBeTrue()
        ->and($labelled->pluck('id')->all())->toEqualCanonicalizing($queued->take(5)->pluck('id')->all());
});

it('does not label an order that is no longer queued when the evaluation is applied', function (): void {
    $first = flowOrder(1);
    $second = flowOrder(5);
    flowOrder(9);

    $evaluation = flowApproved();
    $first->update(['status' => OrderStatus::Done]);

    app(EvaluationWorkflow::class)->apply($evaluation, $this->manager);

    expect($first->fresh()->priority)->toBe(OrderPriority::Normal)
        ->and($second->fresh()->priority)->toBe(OrderPriority::Top)
        ->and(ProductionOrder::query()->where('priority', 'top')->count())->toBe(3 - 1);
});

it('requires approval before applying and lets only the manager and the leader apply', function (): void {
    flowOrder();
    $draft = flowEvaluate();

    flowInvalid(fn () => app(EvaluationWorkflow::class)->apply($draft, $this->manager), 'evaluation');

    app(EvaluationWorkflow::class)->submit($draft, $this->manager);
    flowInvalid(fn () => app(EvaluationWorkflow::class)->apply($draft, $this->manager), 'evaluation');

    app(EvaluationWorkflow::class)->approve($draft, $this->leader);

    foreach ([$this->supervisor, $this->admin] as $user) {
        expect(fn () => app(EvaluationWorkflow::class)->apply($draft, $user))->toThrow(AuthorizationException::class);
    }

    expect(app(EvaluationWorkflow::class)->apply($draft, $this->leader)->status)->toBe(EvaluationStatus::Applied);
});

it('lets the manager apply a draft directly when approval is not required', function (): void {
    Setting::put('approval_required', false);
    flowOrder();

    $evaluation = flowEvaluate();

    expect($evaluation->allowsApply())->toBeTrue();
    expect(app(EvaluationWorkflow::class)->apply($evaluation, $this->manager)->status)->toBe(EvaluationStatus::Applied);
});

it('lets only the newest evaluation be applied and never one twice', function (): void {
    flowOrder();
    $old = flowApproved();
    $new = flowEvaluate();

    flowInvalid(fn () => app(EvaluationWorkflow::class)->apply($old, $this->manager), 'evaluation');
    expect($old->fresh()->allowsApply())->toBeFalse();

    $new->update(['status' => EvaluationStatus::Canceled]);

    app(EvaluationWorkflow::class)->apply($old, $this->manager);
    flowInvalid(fn () => app(EvaluationWorkflow::class)->apply($old, $this->manager), 'evaluation');
});

it('replaces the labels of the previous evaluation when a new one is applied', function (): void {
    Setting::put('top_n_priority', 1);
    $near = flowOrder(1);
    $far = flowOrder(25);

    app(EvaluationWorkflow::class)->apply(flowApproved(), $this->manager);
    expect($near->fresh()->priority)->toBe(OrderPriority::Top);

    $near->update(['deadline_date' => today()->addDays(30)]);
    $far->update(['deadline_date' => today()->addDay()]);

    app(EvaluationWorkflow::class)->apply(flowApproved(), $this->manager);

    expect($near->fresh()->priority)->toBe(OrderPriority::Normal)
        ->and($far->fresh()->priority)->toBe(OrderPriority::Top);
});

it('cancels an evaluation that is not applied and refuses to apply it afterwards', function (): void {
    flowOrder();
    $evaluation = flowEvaluate();

    foreach ([$this->supervisor, $this->admin] as $user) {
        expect(fn () => app(EvaluationWorkflow::class)->cancel($evaluation, $user))->toThrow(AuthorizationException::class);
    }

    $canceled = app(EvaluationWorkflow::class)->cancel($evaluation, $this->leader);

    expect($canceled->status)->toBe(EvaluationStatus::Canceled)
        ->and(ActivityLog::query()->where('action', 'evaluation.canceled')->count())->toBe(1);

    flowInvalid(fn () => app(EvaluationWorkflow::class)->cancel($canceled, $this->manager), 'evaluation');
    flowInvalid(fn () => app(EvaluationWorkflow::class)->apply($canceled, $this->manager), 'evaluation');
    expect($evaluation->fresh()->items()->count())->toBe(1);
});

it('does not cancel an applied evaluation', function (): void {
    flowOrder();
    $evaluation = flowApproved();
    app(EvaluationWorkflow::class)->apply($evaluation, $this->manager);

    flowInvalid(fn () => app(EvaluationWorkflow::class)->cancel($evaluation, $this->manager), 'evaluation');
    expect($evaluation->fresh()->status)->toBe(EvaluationStatus::Applied);
});

it('warns when manual locks and escalations alone fill every slot', function (): void {
    Setting::put('top_n_priority', 2);

    flowOrder(30, [], OrderStatus::InProgress);
    flowOrder(20, ['lock_type' => LockType::Manual, 'lock_reason' => 'Penting', 'lock_until' => null]);
    flowOrder(20, ['order_date' => today()->subDays(9)]);
    flowOrder(5);

    $evaluation = flowEvaluate();
    $plan = app(EvaluationWorkflow::class)->planFor($evaluation);

    expect($plan->reserved)->toBe(2)
        ->and($plan->slots)->toBe(2)
        ->and($plan->isFull())->toBeTrue()
        ->and($plan->topOrderIds)->toHaveCount(2);

    Setting::put('top_n_priority', 3);
    $bigger = app(EvaluationWorkflow::class)->planFor(flowEvaluate());

    expect($bigger->isFull())->toBeFalse()->and($bigger->topOrderIds)->toHaveCount(3);
});

it('does not count orders being worked on against the slots', function (): void {
    Setting::put('top_n_priority', 1);

    foreach (range(1, 3) as $i) {
        flowOrder(30, [], OrderStatus::InProgress);
    }
    flowOrder(5);

    $plan = app(EvaluationWorkflow::class)->planFor(flowEvaluate());

    expect($plan->reserved)->toBe(0)->and($plan->isFull())->toBeFalse()->and($plan->topOrderIds)->toHaveCount(1);
});

it('moves an order to another place, shifts the others, and keeps the system rank', function (): void {
    $orders = collect([2, 6, 10, 14, 18])->map(fn (int $days) => flowOrder($days));
    $evaluation = flowEvaluate();

    $before = $evaluation->items()->orderBy('final_rank')->pluck('production_order_id')->all();
    $item = flowItem($evaluation, ProductionOrder::query()->findOrFail($before[0]));

    app(EvaluationAdjuster::class)->move($item, 4, ' Pelanggan minta didahulukan ', $this->manager);

    $after = $evaluation->items()->orderBy('final_rank')->get();

    expect($after->pluck('production_order_id')->all())->toBe([$before[1], $before[2], $before[3], $before[0], $before[4]])
        ->and($after->pluck('final_rank')->all())->toBe([1, 2, 3, 4, 5])
        ->and($after->firstWhere('production_order_id', $before[0])->adjustment_reason)->toBe('Pelanggan minta didahulukan')
        ->and($after->firstWhere('production_order_id', $before[0])->system_rank)->toBe(1)
        ->and($after->firstWhere('production_order_id', $before[1])->adjustment_reason)->toBeNull();

    $log = ActivityLog::query()->where('action', 'evaluation.rank_adjusted')->sole();
    expect($log->new_values)->toMatchArray(['final_rank' => 4, 'reason' => 'Pelanggan minta didahulukan'])
        ->and($log->old_values['final_rank'])->toBe(1)
        ->and($orders->count())->toBe(5);

    $last = flowItem($evaluation, ProductionOrder::query()->findOrFail($before[4]));
    app(EvaluationAdjuster::class)->move($last, 1, 'Ke atas', $this->leader);

    expect($evaluation->items()->orderBy('final_rank')->first()->production_order_id)->toBe($before[4]);
});

it('needs a reason, a rank inside the list, and a different rank', function (): void {
    foreach ([2, 6, 10] as $days) {
        flowOrder($days);
    }
    $evaluation = flowEvaluate();
    $item = $evaluation->items()->orderBy('final_rank')->first();

    flowInvalid(fn () => app(EvaluationAdjuster::class)->move($item, 2, '', $this->manager), 'reason');
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move($item, 2, str_repeat('a', 256), $this->manager), 'reason');
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move($item, 0, 'Alasan', $this->manager), 'rank');
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move($item, 4, 'Alasan', $this->manager), 'rank');
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move($item, 1, 'Alasan', $this->manager), 'rank');

    expect($evaluation->items()->orderBy('final_rank')->pluck('final_rank')->all())->toBe([1, 2, 3])
        ->and(EvaluationItem::query()->whereNotNull('adjustment_reason')->count())->toBe(0);
});

it('lets only the manager and the leader adjust, and only a draft or submitted evaluation', function (): void {
    foreach ([2, 6] as $days) {
        flowOrder($days);
    }
    $evaluation = flowEvaluate();
    $item = $evaluation->items()->orderBy('final_rank')->first();

    foreach ([$this->supervisor, $this->admin] as $user) {
        expect(fn () => app(EvaluationAdjuster::class)->move($item, 2, 'Alasan', $user))->toThrow(AuthorizationException::class);
    }

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationAdjuster::class)->move($item->fresh(), 2, 'Saat diajukan', $this->manager);

    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move($item->fresh(), 1, 'Setelah disetujui', $this->manager), 'rank');
});

it('does not let a locked order drop below an ordinary one or an ordinary one rise above a locked one', function (): void {
    $locked = flowOrder(30, [], OrderStatus::InProgress);
    $regular = flowOrder(2);
    $other = flowOrder(8);

    $evaluation = flowEvaluate();

    expect(flowItem($evaluation, $locked)->rank_group)->toBe(RankGroup::Locked)
        ->and(flowItem($evaluation, $locked)->final_rank)->toBe(1);

    flowInvalid(fn () => app(EvaluationAdjuster::class)->move(flowItem($evaluation, $locked), 2, 'Turunkan', $this->manager), 'rank');
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move(flowItem($evaluation, $locked), 3, 'Turunkan', $this->manager), 'rank');
    flowInvalid(fn () => app(EvaluationAdjuster::class)->move(flowItem($evaluation, $other), 1, 'Naikkan', $this->manager), 'rank');

    expect(flowItem($evaluation, $locked)->final_rank)->toBe(1)
        ->and($regular->exists)->toBeTrue();
});

it('allows moving inside the locked group and an escalated order above a locked one', function (): void {
    $lockedA = flowOrder(30, [], OrderStatus::InProgress);
    $lockedB = flowOrder(25, [], OrderStatus::InProgress);
    $escalated = flowOrder(30, ['order_date' => today()->subDays(10)]);
    flowOrder(3);

    $evaluation = flowEvaluate();
    $first = $evaluation->items()->orderBy('final_rank')->first();

    app(EvaluationAdjuster::class)->move($first, 2, 'Tukar sesama terkunci', $this->manager);
    expect($evaluation->items()->orderBy('final_rank')->get()->take(2)->pluck('rank_group')->all())->toBe([RankGroup::Locked, RankGroup::Locked]);

    app(EvaluationAdjuster::class)->move(flowItem($evaluation, $escalated), 1, 'Dinaikkan pimpinan', $this->leader);

    expect(flowItem($evaluation, $escalated)->final_rank)->toBe(1)
        ->and($lockedA->exists && $lockedB->exists)->toBeTrue();
});
