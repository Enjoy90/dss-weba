<?php

use App\Enums\EvaluationStatus;
use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Filament\Pages\AssessmentMatrix;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Widgets\HeldOrdersWidget;
use App\Filament\Widgets\LateOrdersWidget;
use App\Filament\Widgets\LatestEvaluationWidget;
use App\Filament\Widgets\OrderStatsOverview;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\PendingApprovalWidget;
use App\Filament\Widgets\TopPriorityWidget;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dashboard\DashboardFigures;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\EvaluationWorkflow;
use App\Services\Evaluation\HeldOrders;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->admin = User::factory()->admin()->create();
});

/**
 * An open order with fresh manual values. A nearer deadline ranks higher, price and urgency are fixed.
 */
function dashOrder(int $dueInDays = 20, array $state = []): ProductionOrder
{
    $state += ['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100];
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

/**
 * Six orders that rank above one slow order, then enough evaluations for the slow one to be held.
 * Returns the slow order, which entered five days ago and is far from its deadline.
 *
 * @return array{0: ProductionOrder, 1: array<int, ProductionOrder>}
 */
function dashHeldSetup(int $evaluations = 5): array
{
    $strong = [];

    foreach ([2, 3, 4, 5, 6, 7] as $days) {
        $strong[] = dashOrder($days, ['order_date' => today()->subDays(1)]);
    }

    $slow = dashOrder(60, ['order_date' => today()->subDays(5)]);

    for ($i = 0; $i < $evaluations; $i++) {
        app(EvaluationService::class)->run(test()->manager);
    }

    return [$slow, $strong];
}

it('opens the dashboard for every role', function (): void {
    foreach ([$this->admin, $this->manager, $this->supervisor, $this->leader] as $user) {
        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Dasbor');
    }
});

it('counts every card with the same scope as the filter it links to', function (): void {
    dashOrder(-3);
    dashOrder(-9);
    dashOrder(2);
    dashOrder(30);
    dashOrder(40, ['status' => OrderStatus::InProgress]);
    dashOrder(25, ['lock_type' => LockType::Manual, 'lock_reason' => 'Permintaan pimpinan', 'locked_by' => $this->leader->id, 'locked_at' => now()]);
    dashOrder(20, ['status' => OrderStatus::Done]);

    $counts = app(DashboardFigures::class)->counts();

    expect($counts['active'])->toBe(6)
        ->and($counts['late'])->toBe(2)
        ->and($counts['near'])->toBe(1)
        ->and($counts['locked'])->toBe(2)
        ->and($counts['held'])->toBe(0);

    $this->actingAs($this->manager);

    Livewire::test(ListProductionOrders::class)->filterTable('late', true)->assertCountTableRecords($counts['late']);
    Livewire::test(ListProductionOrders::class)->filterTable('near_deadline', true)->assertCountTableRecords($counts['near']);
    Livewire::test(ListProductionOrders::class)->filterTable('locked', true)->assertCountTableRecords($counts['locked']);
    Livewire::test(ListProductionOrders::class)->filterTable('status', ['queued', 'in_progress'])->assertCountTableRecords($counts['active']);
});

it('counts a lapsed manual lock out of the locked card', function (): void {
    dashOrder(25, ['lock_type' => LockType::Manual, 'lock_reason' => 'Sudah berakhir', 'locked_by' => $this->leader->id, 'locked_at' => now()->subDays(5), 'lock_until' => today()->subDay()]);

    expect(app(DashboardFigures::class)->counts()['locked'])->toBe(0);
});

it('counts incomplete assessments like the filter on the assessment page', function (): void {
    dashOrder(10);
    ProductionOrder::factory()->dueInDays(12)->create();

    $counts = app(DashboardFigures::class)->counts();
    expect($counts['incomplete'])->toBeGreaterThan(0);

    $this->actingAs($this->manager);

    Livewire::test(AssessmentMatrix::class)->filterTable('incomplete', true)->assertCountTableRecords($counts['incomplete']);
});

it('opens the order list with the filter of a card already on', function (): void {
    dashOrder(-3);
    dashOrder(-9);
    dashOrder(30);

    $this->actingAs($this->manager);

    $widget = Livewire::test(OrderStatsOverview::class);
    $widget->assertSee('Terlambat')->assertSee('Order aktif');

    Livewire::withQueryParams(['filters' => ['late' => ['isActive' => true]]])
        ->test(ListProductionOrders::class)
        ->assertCountTableRecords(2);

    Livewire::withQueryParams(['filters' => ['status' => ['values' => ['queued', 'in_progress']]]])
        ->test(ListProductionOrders::class)
        ->assertCountTableRecords(3);

    Livewire::withQueryParams(['filters' => ['incomplete' => ['isActive' => true]]])
        ->test(AssessmentMatrix::class)
        ->assertCountTableRecords(0);
});

it('links every card to a list that holds exactly the counted rows', function (): void {
    [$slow] = dashHeldSetup();
    dashOrder(-3);
    dashOrder(-9);
    dashOrder(2);
    dashOrder(40, ['status' => OrderStatus::InProgress]);
    dashOrder(25, ['lock_type' => LockType::Manual, 'lock_reason' => 'Permintaan pimpinan', 'locked_by' => $this->leader->id, 'locked_at' => now()]);
    ProductionOrder::factory()->dueInDays(12)->create();

    $this->actingAs($this->manager);
    $stats = (new ReflectionMethod(OrderStatsOverview::class, 'getStats'))->invoke(new OrderStatsOverview);

    expect($stats)->toHaveCount(6);

    foreach ($stats as $stat) {
        $url = parse_url($stat->getUrl());
        parse_str($url['query'], $query);
        $page = str_ends_with($url['path'], '/penilaian-order') ? AssessmentMatrix::class : ListProductionOrders::class;

        Livewire::withQueryParams($query)->test($page)->assertCountTableRecords($stat->getValue());
    }

    expect(collect($stats)->map(fn ($stat) => $stat->getValue())->sum())->toBeGreaterThan(10);
});

it('marks an order as held only after enough evaluations in a row outside the priority list', function (): void {
    [$slow] = dashHeldSetup();

    expect(app(HeldOrders::class)->ids())->toBe([$slow->id])
        ->and(app(DashboardFigures::class)->counts()['held'])->toBe(1);

    $this->actingAs($this->manager);
    Livewire::test(ListProductionOrders::class)->filterTable('held', true)->assertCountTableRecords(1);
});

it('does not hold an order after only four evaluations', function (): void {
    dashHeldSetup(4);

    expect(app(HeldOrders::class)->ids())->toBe([]);
});

it('does not let orders already being worked on push a queued order out of the priority list', function (): void {
    foreach ([2, 3, 4, 5, 6, 7] as $days) {
        dashOrder($days, ['status' => OrderStatus::InProgress]);
    }

    $queued = dashOrder(60, ['order_date' => today()->subDays(5)]);

    for ($i = 0; $i < 5; $i++) {
        app(EvaluationService::class)->run($this->manager);
    }

    // The queued order is rank 7 overall but the best queued order, so it is inside the list of five.
    expect(app(HeldOrders::class)->ids())->toBe([])
        ->and($queued->fresh()->status)->toBe(OrderStatus::Queued);
});

it('does not hold an order that has waited fewer days than the minimum', function (): void {
    [$slow] = dashHeldSetup();
    $slow->forceFill(['order_date' => today()->subDays(3)])->saveQuietly();

    expect(app(HeldOrders::class)->ids())->toBe([]);

    $slow->forceFill(['order_date' => today()->subDays(4)])->saveQuietly();

    expect(app(HeldOrders::class)->ids())->toBe([$slow->id]);
});

it('breaks the streak when the order was inside the priority list once', function (): void {
    [$slow] = dashHeldSetup();
    $evaluation = Evaluation::query()->orderBy('id')->skip(2)->first();
    DB::table('evaluation_items')->where('evaluation_id', $evaluation->id)->where('production_order_id', $slow->id)->update(['final_rank' => 3]);

    expect(app(HeldOrders::class)->ids())->toBe([]);
});

it('does not count a canceled evaluation toward the streak', function (): void {
    [$slow] = dashHeldSetup();
    $evaluation = Evaluation::query()->latest('id')->first();
    app(EvaluationWorkflow::class)->cancel($evaluation, $this->manager);

    expect(app(HeldOrders::class)->ids())->toBe([]);
});

it('follows the settings for the number of evaluations and the days', function (): void {
    [$slow] = dashHeldSetup(3);

    expect(app(HeldOrders::class)->ids())->toBe([]);

    Setting::put('held_after_evaluations', 3);
    expect(app(HeldOrders::class)->ids())->toBe([$slow->id]);

    Setting::put('held_min_days', 6);
    expect(app(HeldOrders::class)->ids())->toBe([]);
});

it('leaves out a held order that is locked, being worked on, or finished', function (): void {
    [$slow] = dashHeldSetup();

    $slow->forceFill(['lock_type' => LockType::Manual, 'lock_reason' => 'Ditahan pimpinan', 'locked_by' => $this->leader->id, 'locked_at' => now()])->saveQuietly();
    expect(app(HeldOrders::class)->ids())->toBe([]);

    $slow->forceFill(['lock_type' => LockType::None, 'lock_reason' => null, 'locked_by' => null, 'locked_at' => null])->saveQuietly();
    expect(app(HeldOrders::class)->ids())->toBe([$slow->id]);

    $slow->update(['status' => OrderStatus::InProgress]);
    expect(app(HeldOrders::class)->ids())->toBe([]);

    $slow->fresh()->update(['status' => OrderStatus::Done]);
    expect(app(HeldOrders::class)->ids())->toBe([]);
});

it('lists held and escalated orders on the dashboard with the reason', function (): void {
    [$slow] = dashHeldSetup();
    $old = dashOrder(90, ['order_date' => today()->subDays(9)]);
    app(EvaluationService::class)->run($this->manager);

    $reasons = app(HeldOrders::class)->watchList();

    // The old order passed the wait limit, the slow order only waited five days and is still held.
    expect($reasons->get($old->id))->toBe('escalated')
        ->and($reasons->get($slow->id))->toBe('held');

    $this->actingAs($this->manager);
    Livewire::test(HeldOrdersWidget::class)
        ->assertCanSeeTableRecords([$old, $slow])
        ->assertSee('Dieskalasi')
        ->assertSee('Tertahan');
});

it('shows a held order as Tertahan in the dashboard list', function (): void {
    [$slow] = dashHeldSetup();

    $this->actingAs($this->manager);
    Livewire::test(HeldOrdersWidget::class)
        ->assertCanSeeTableRecords([$slow])
        ->assertSee('Tertahan')
        ->assertSee('5 hari');
});

it('shows an empty message when nothing is held', function (): void {
    dashOrder(10);

    $this->actingAs($this->manager);
    Livewire::test(HeldOrdersWidget::class)->assertSee('Tidak ada order tertahan');
});

it('lists the most overdue orders first and at most five', function (): void {
    foreach ([-2, -4, -6, -8, -10, -12, -1] as $days) {
        dashOrder($days);
    }

    $this->actingAs($this->manager);
    $all = ProductionOrder::query()->late()->orderBy('deadline_date')->get();

    Livewire::test(LateOrdersWidget::class)
        ->assertCanSeeTableRecords($all->take(5), inOrder: true)
        ->assertCanNotSeeTableRecords($all->slice(5));
});

it('shows the label orders of the latest applied evaluation on the dashboard', function (): void {
    $orders = collect([3, 4, 5, 6, 7, 8, 9])->map(fn (int $days): ProductionOrder => dashOrder($days));
    $evaluation = app(EvaluationService::class)->run($this->manager);
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);
    app(EvaluationWorkflow::class)->apply($evaluation->fresh(), $this->manager);

    $top = ProductionOrder::query()->where('priority', OrderPriority::Top->value)->get();
    expect($top)->toHaveCount(5);

    $items = $evaluation->items()->whereIn('production_order_id', $top->pluck('id'))->get();

    $this->actingAs($this->supervisor);
    Livewire::test(TopPriorityWidget::class)
        ->assertCanSeeTableRecords($items, inOrder: true)
        ->assertCountTableRecords(5)
        ->assertSee($evaluation->name);
});

it('tells that nothing is applied yet in the priority list', function (): void {
    dashOrder(5);
    app(EvaluationService::class)->run($this->manager);

    $this->actingAs($this->manager);
    Livewire::test(TopPriorityWidget::class)->assertSee('Belum ada evaluasi yang diterapkan');
});

it('drops a finished order from the priority list', function (): void {
    $orders = collect([3, 4, 5])->map(fn (int $days): ProductionOrder => dashOrder($days));
    $evaluation = app(EvaluationService::class)->run($this->manager);
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);
    app(EvaluationWorkflow::class)->apply($evaluation->fresh(), $this->manager);

    $orders[0]->fresh()->update(['status' => OrderStatus::Done]);

    $this->actingAs($this->manager);
    Livewire::test(TopPriorityWidget::class)->assertCountTableRecords(2);
});

it('counts the orders of every status in the chart', function (): void {
    dashOrder(10);
    dashOrder(11);
    dashOrder(12, ['status' => OrderStatus::InProgress]);
    dashOrder(13, ['status' => OrderStatus::Done]);
    dashOrder(14, ['status' => OrderStatus::Canceled]);
    dashOrder(15, ['status' => OrderStatus::Canceled]);

    $this->actingAs($this->manager);
    $chart = new OrderStatusChart;

    expect($chart->counts())->toBe(['queued' => 2, 'in_progress' => 1, 'done' => 1, 'canceled' => 2]);
});

it('shows only the leader the evaluations that wait for approval', function (): void {
    dashOrder(10);
    $evaluation = app(EvaluationService::class)->run($this->manager);
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    $this->actingAs($this->leader);
    $this->get('/admin')->assertOk()->assertSeeLivewire(PendingApprovalWidget::class);
    Livewire::test(PendingApprovalWidget::class)->assertCanSeeTableRecords([$evaluation]);

    foreach ([$this->manager, $this->supervisor, $this->admin] as $user) {
        $this->actingAs($user)->get('/admin')->assertOk()->assertDontSeeLivewire(PendingApprovalWidget::class);
    }
});

it('shows the run shortcut to the manager only', function (): void {
    dashOrder(10);

    $this->actingAs($this->manager);
    Livewire::test(LatestEvaluationWidget::class)->assertSee('Belum ada perhitungan prioritas')->assertSee('Jalankan Perhitungan');

    foreach ([$this->leader, $this->supervisor, $this->admin] as $user) {
        $this->actingAs($user);
        Livewire::test(LatestEvaluationWidget::class)->assertDontSee('Jalankan Perhitungan');
    }
});

it('shows the newest evaluation that is not canceled', function (): void {
    dashOrder(10);
    $first = app(EvaluationService::class)->run($this->manager);
    $second = app(EvaluationService::class)->run($this->manager);
    app(EvaluationWorkflow::class)->cancel($second, $this->manager);

    $this->actingAs($this->manager);
    $widget = new LatestEvaluationWidget;

    expect($widget->latest()->is($first))->toBeTrue()
        ->and($first->status)->toBe(EvaluationStatus::Draft);
});

it('shows no rupiah to the supervisor on the dashboard', function (): void {
    dashOrder(5);
    dashOrder(-3);
    $evaluation = app(EvaluationService::class)->run($this->manager);
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    $this->actingAs($this->supervisor);
    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)->not->toContain('Rp ')->not->toContain('Rp&nbsp;');
});
