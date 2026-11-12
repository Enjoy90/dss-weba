<?php

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SystemSettings;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->leader = User::factory()->leader()->create();
    $this->supervisor = User::factory()->supervisor()->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function scheduleOrders(int $count = 3): void
{
    test()->seed(CriteriaSeeder::class);
    $criteria = Criterion::query()->get()->keyBy('code');

    foreach (range(1, $count) as $n) {
        $order = ProductionOrder::factory()->dueInDays(2 + $n * 3)->create(['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100]);

        foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
            Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => $criteria[$code]->id, 'raw_value' => $value, 'value_updated_at' => now()]);
        }
    }
}

function scheduleAt(string $time): void
{
    Carbon::setTestNow(Carbon::parse(today()->toDateString().' '.$time, 'Asia/Jakarta'));
}

it('registers the scheduled evaluation and the notification cleanup in the scheduler', function (): void {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('evaluation:run-scheduled')->toContain('notifications:prune');
});

it('does nothing before the hour in the settings', function (): void {
    scheduleOrders();
    scheduleAt('05:59');

    Artisan::call('evaluation:run-scheduled');

    expect(Evaluation::query()->count())->toBe(0);
});

it('creates a draft evaluation with the scheduled trigger once the hour has come', function (): void {
    scheduleOrders();
    scheduleAt('06:00');

    $exit = Artisan::call('evaluation:run-scheduled');
    $evaluation = Evaluation::query()->first();

    expect($exit)->toBe(0)
        ->and($evaluation->trigger)->toBe(EvaluationTrigger::Scheduled)
        ->and($evaluation->status)->toBe(EvaluationStatus::Draft)
        ->and($evaluation->evaluated_by)->toBeNull()
        ->and($evaluation->order_count)->toBe(3)
        ->and($evaluation->name)->toContain('Evaluasi terjadwal');

    $log = ActivityLog::query()->where('action', 'evaluation.created')->first();
    expect($log->user_id)->toBeNull();
});

it('follows the hour that was set', function (): void {
    scheduleOrders();
    SystemSettings::save(['schedule_time' => '09:30'], $this->admin);

    scheduleAt('09:29');
    Artisan::call('evaluation:run-scheduled');
    expect(Evaluation::query()->count())->toBe(0);

    scheduleAt('09:30');
    Artisan::call('evaluation:run-scheduled');
    expect(Evaluation::query()->count())->toBe(1);
});

it('does nothing when the schedule is switched off', function (): void {
    scheduleOrders();
    SystemSettings::save(['schedule_enabled' => false], $this->admin);
    scheduleAt('07:00');

    Artisan::call('evaluation:run-scheduled');

    expect(Evaluation::query()->count())->toBe(0);
});

it('runs only once a day even if the command is called every minute, and again the next day', function (): void {
    scheduleOrders();

    scheduleAt('06:00');
    Artisan::call('evaluation:run-scheduled');
    scheduleAt('06:01');
    Artisan::call('evaluation:run-scheduled');
    scheduleAt('13:00');
    Artisan::call('evaluation:run-scheduled');

    expect(Evaluation::query()->count())->toBe(1);

    Carbon::setTestNow(Carbon::parse(today()->addDay()->toDateString().' 06:02', 'Asia/Jakarta'));
    Artisan::call('evaluation:run-scheduled');

    expect(Evaluation::query()->count())->toBe(2);
});

it('still runs when the minute of the hour was missed', function (): void {
    scheduleOrders();
    scheduleAt('06:03');

    Artisan::call('evaluation:run-scheduled');

    expect(Evaluation::query()->count())->toBe(1);
});

it('can be forced at any time and ignores the switch', function (): void {
    scheduleOrders();
    SystemSettings::save(['schedule_enabled' => false], $this->admin);
    scheduleAt('03:00');

    Artisan::call('evaluation:run-scheduled', ['--force' => true]);

    expect(Evaluation::query()->count())->toBe(1);
});

it('does not create an evaluation without active orders, logs it, and tells the admin and the manager', function (): void {
    $this->seed(CriteriaSeeder::class);
    ProductionOrder::factory()->create(['status' => OrderStatus::Done]);
    scheduleAt('06:00');

    $exit = Artisan::call('evaluation:run-scheduled');

    expect($exit)->toBe(0)->and(Evaluation::query()->count())->toBe(0);

    $log = ActivityLog::query()->where('action', 'evaluation.schedule_skipped')->first();
    expect($log->user_id)->toBeNull()
        ->and($log->new_values['reason'])->toContain('Tidak ada order')
        ->and($log->actionLabel())->toBe('Perhitungan terjadwal dilewati');

    foreach ([$this->admin, $this->manager] as $user) {
        expect($user->notifications()->count())->toBe(1)
            ->and($user->notifications()->first()->data['title'])->toBe('Perhitungan terjadwal tidak berjalan');
    }

    expect($this->leader->notifications()->count())->toBe(0)->and($this->supervisor->notifications()->count())->toBe(0);
});

it('logs a failed scheduled calculation and tells the admin and the manager', function (): void {
    ProductionOrder::factory()->create();
    scheduleAt('06:00');

    $exit = Artisan::call('evaluation:run-scheduled');

    expect($exit)->toBe(1)->and(Evaluation::query()->count())->toBe(0);

    $log = ActivityLog::query()->where('action', 'evaluation.schedule_failed')->first();
    expect($log->new_values['reason'])->toContain('Perhitungan terjadwal gagal')
        ->and($this->admin->notifications()->count())->toBe(1)
        ->and($this->manager->notifications()->count())->toBe(1);
});

it('does not try again every minute after a failure on the same day', function (): void {
    ProductionOrder::factory()->create();

    scheduleAt('06:00');
    Artisan::call('evaluation:run-scheduled');
    scheduleAt('06:01');
    Artisan::call('evaluation:run-scheduled');

    expect(ActivityLog::query()->where('action', 'evaluation.schedule_failed')->count())->toBe(1);
});

it('goes on with incomplete orders because nobody is there to confirm', function (): void {
    scheduleOrders(2);
    ProductionOrder::factory()->dueInDays(10)->create();
    scheduleAt('06:00');

    Artisan::call('evaluation:run-scheduled');

    expect(Evaluation::query()->first()->order_count)->toBe(3);
});

it('keeps the scheduled evaluation in the approval flow', function (): void {
    scheduleOrders();
    scheduleAt('06:00');
    Artisan::call('evaluation:run-scheduled');
    $evaluation = Evaluation::query()->first();

    expect($evaluation->allowsSubmit())->toBeTrue()->and($evaluation->allowsApply())->toBeFalse();
});

it('removes notifications older than 30 days and keeps the newer ones, read or not', function (): void {
    foreach ([40, 31, 29, 1] as $days) {
        $this->manager->notify(new class extends Notification
        {
            public function via(): array
            {
                return ['database'];
            }

            public function toArray(): array
            {
                return ['title' => 'uji'];
            }
        });
        $notification = $this->manager->notifications()->latest('created_at')->latest('id')->first();
        $notification->forceFill(['created_at' => now()->subDays($days), 'read_at' => $days === 40 ? now() : null])->save();
    }

    Artisan::call('notifications:prune');

    $ages = DatabaseNotification::query()->get()->map(fn ($n) => (int) round(now()->diffInDays($n->created_at, true)))->sort()->values()->all();

    expect($ages)->toBe([1, 29]);
});

it('keeps the last run date in the settings', function (): void {
    scheduleOrders();
    scheduleAt('06:00');
    Artisan::call('evaluation:run-scheduled');

    expect(Setting::get('schedule_last_run'))->toBe(today()->toDateString());
});
