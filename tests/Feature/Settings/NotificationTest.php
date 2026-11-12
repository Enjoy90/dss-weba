<?php

use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\EvaluationWorkflow;
use App\Services\Notifications\EvaluationNotifier;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
});

/**
 * An open order with manual values. A nearer deadline ranks higher, price and urgency are fixed.
 */
function noteOrder(int $dueInDays = 20, array $state = [], int $valueAgeDays = 0): ProductionOrder
{
    $state += ['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100];
    $order = ProductionOrder::factory()->dueInDays($dueInDays)->create($state);

    foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
        Assessment::factory()->create([
            'production_order_id' => $order->id,
            'criterion_id' => test()->criteria[$code]->id,
            'raw_value' => $value,
            'value_updated_at' => now()->subDays($valueAgeDays),
        ]);
    }

    return $order;
}

function noteEvaluate(): Evaluation
{
    return app(EvaluationService::class)->run(test()->manager, acknowledgeWarnings: true);
}

/**
 * The codes (N1 to N10) of the notifications a user received, oldest first.
 *
 * @return array<int, string>
 */
function noteCodes(User $user): array
{
    return $user->notifications()->oldest('created_at')->get()->map(fn ($n): ?string => $n->data['viewData']['code'] ?? null)->all();
}

function noteClear(): void
{
    DatabaseNotification::query()->delete();
}

it('saves the notification right away, without waiting for a queue worker', function (): void {
    config(['queue.default' => 'database']);
    noteOrder();

    app(EvaluationWorkflow::class)->submit(noteEvaluate(), $this->manager);

    expect(noteCodes($this->leader))->toBe(['N1'])
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('turns on the bell in the top bar', function (): void {
    expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue();
});

it('tells the leaders that an evaluation waits for approval (N1)', function (): void {
    noteOrder();
    $evaluation = noteEvaluate();

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    $notification = $this->leader->notifications()->first();

    expect(noteCodes($this->leader))->toBe(['N1'])
        ->and($notification->data['title'])->toBe('Evaluasi menunggu persetujuan Anda')
        ->and($notification->data['body'])->toContain($evaluation->name)
        ->and($notification->data['actions'][0]['url'])->toContain('/admin/')
        ->and($notification->read_at)->toBeNull()
        ->and(noteCodes($this->manager))->toBe([])
        ->and(noteCodes($this->supervisor))->toBe([])
        ->and(noteCodes($this->admin))->toBe([]);
});

it('tells the one who submitted that the evaluation was approved (N2)', function (): void {
    noteOrder();
    $otherManager = User::factory()->manager()->create();
    $evaluation = noteEvaluate();

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    expect(noteCodes($this->manager))->toBe(['N2'])
        ->and(noteCodes($otherManager))->toBe([]);
});

it('tells the one who submitted that the evaluation was rejected, with the note (N3)', function (): void {
    noteOrder();
    $evaluation = noteEvaluate();

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->reject($evaluation, $this->leader, 'Bobot C2 perlu dicek lagi');

    $notification = $this->manager->notifications()->first();

    expect(noteCodes($this->manager))->toBe(['N3'])
        ->and($notification->data['body'])->toContain('Bobot C2 perlu dicek lagi');
});

it('tells the supervisors that a new priority list applies (N4)', function (): void {
    noteOrder();
    $evaluation = noteEvaluate();

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);
    noteClear();
    app(EvaluationWorkflow::class)->apply($evaluation->fresh(), $this->manager);

    expect(noteCodes($this->supervisor))->toBe(['N4'])
        ->and($this->supervisor->notifications()->first()->data['actions'][0]['url'])->toContain('priority')
        ->and(noteCodes($this->manager))->toBe([])
        ->and(noteCodes($this->leader))->toBe([]);
});

it('also tells the supervisors when a draft is applied without approval', function (): void {
    Setting::put('approval_required', false);
    noteOrder();

    app(EvaluationWorkflow::class)->apply(noteEvaluate(), $this->manager);

    expect(noteCodes($this->supervisor))->toBe(['N4']);
});

it('sends nothing for a canceled evaluation', function (): void {
    noteOrder();

    app(EvaluationWorkflow::class)->cancel(noteEvaluate(), $this->manager);

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('skips users that are switched off', function (): void {
    noteOrder();
    $this->leader->update(['is_active' => false]);
    $activeLeader = User::factory()->leader()->create();

    app(EvaluationWorkflow::class)->submit(noteEvaluate(), $this->manager);

    expect(noteCodes($this->leader))->toBe([])->and(noteCodes($activeLeader))->toBe(['N1']);
});

it('tells the managers about late orders after a calculation (N5), and only when there are some', function (): void {
    noteOrder();
    noteEvaluate();
    expect(noteCodes($this->manager))->toBe([]);

    noteOrder(-3);
    noteOrder(-1);
    noteEvaluate();

    $notification = $this->manager->notifications()->first();

    expect(noteCodes($this->manager))->toBe(['N5'])
        ->and($notification->data['body'])->toStartWith('2 order')
        ->and(noteCodes($this->leader))->toBe([])
        ->and(noteCodes($this->supervisor))->toBe([]);
});

it('tells the managers about outdated manual values (N6)', function (): void {
    noteOrder();
    noteOrder(25, valueAgeDays: 40);

    noteEvaluate();

    expect(noteCodes($this->manager))->toBe(['N6'])
        ->and($this->manager->notifications()->first()->data['body'])->toStartWith('1 order')
        ->and(noteCodes($this->leader))->toBe([]);
});

it('tells the managers and leaders once when an order becomes held (N8)', function (): void {
    foreach ([2, 3, 4, 5, 6, 7] as $days) {
        noteOrder($days, ['order_date' => today()->subDays(1)]);
    }

    $slow = noteOrder(60, ['order_date' => today()->subDays(5)]);

    for ($i = 0; $i < 4; $i++) {
        noteEvaluate();
    }

    expect(noteCodes($this->manager))->toBe([]);

    noteEvaluate();

    foreach ([$this->manager, $this->leader] as $user) {
        expect(noteCodes($user))->toBe(['N8'])
            ->and($user->notifications()->first()->data['body'])->toContain($slow->number);
    }

    expect(noteCodes($this->supervisor))->toBe([])->and(noteCodes($this->admin))->toBe([]);

    // Still held after the next calculation, but no longer new.
    noteEvaluate();

    expect(noteCodes($this->manager))->toBe(['N8']);
});

it('tells the managers and leaders once when an order is escalated (N9)', function (): void {
    noteOrder(30);
    $old = noteOrder(90, ['order_date' => today()->subDays(9)]);

    noteEvaluate();

    foreach ([$this->manager, $this->leader] as $user) {
        expect(noteCodes($user))->toBe(['N9'])
            ->and($user->notifications()->first()->data['body'])->toContain($old->number);
    }

    noteEvaluate();

    expect(noteCodes($this->manager))->toBe(['N9']);
});

it('warns when escalated orders use every Prioritas Utama slot (N10)', function (): void {
    Setting::put('top_n_priority', 2);
    noteOrder(5);
    noteOrder(90, ['order_date' => today()->subDays(9)]);

    noteEvaluate();
    expect(noteCodes($this->leader))->toBe(['N9']);

    noteClear();
    noteOrder(80, ['order_date' => today()->subDays(10)]);
    noteEvaluate();

    $full = $this->leader->notifications()->get()->first(fn ($n): bool => $n->data['viewData']['code'] === 'N10');

    // Both are saved in the same second, so their order is not fixed.
    expect(noteCodes($this->leader))->toEqualCanonicalizing(['N9', 'N10'])
        ->and($full->data['body'])->toContain('semua 2 slot')
        ->and(noteCodes($this->manager))->toEqualCanonicalizing(['N9', 'N10']);
});

it('shortens a long list of order numbers', function (): void {
    foreach (range(1, 7) as $n) {
        noteOrder(90 + $n, ['order_date' => today()->subDays(9)]);
    }

    noteEvaluate();

    $body = $this->leader->notifications()->get()->first(fn ($n): bool => $n->data['viewData']['code'] === 'N9')->data['body'];

    expect($body)->toStartWith('7 order')->toContain('dan 2 lainnya');
});

it('keeps the step and the calculation when sending a notification fails', function (): void {
    app()->instance(EvaluationNotifier::class, new class extends EvaluationNotifier
    {
        public function afterStep(string $action, Evaluation $evaluation, ?int $submitterId): void
        {
            throw new RuntimeException('Gagal mengirim');
        }

        public function afterCalculation(Evaluation $evaluation, array $heldBefore, array $heldNow, int $staleOrders): void
        {
            throw new RuntimeException('Gagal mengirim');
        }
    });

    noteOrder(-2);
    $evaluation = noteEvaluate();
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    expect($evaluation->fresh()->status->value)->toBe('submitted')
        ->and(DatabaseNotification::query()->count())->toBe(0);
});

it('marks a notification as read when a user opens it', function (): void {
    noteOrder();
    app(EvaluationWorkflow::class)->submit(noteEvaluate(), $this->manager);

    $notification = $this->leader->notifications()->first();
    $notification->markAsRead();

    expect($this->leader->unreadNotifications()->count())->toBe(0)
        ->and($notification->data['actions'][0]['shouldMarkAsRead'] ?? false)->toBeTrue();
});
