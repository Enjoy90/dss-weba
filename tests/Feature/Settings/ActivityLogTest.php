<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ScaleSettings;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use App\Services\CriterionWeights;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->leader = User::factory()->leader()->create();
});

it('logs a login and a logout with the user', function (): void {
    $user = User::factory()->manager()->create(['email' => 'budi@spk.test']);

    Livewire::test(Login::class)->fillForm(['email' => 'budi@spk.test', 'password' => 'password'])->call('authenticate');

    $login = ActivityLog::query()->where('action', 'auth.login')->first();
    expect($login->user_id)->toBe($user->id)->and($login->actionLabel())->toBe('Masuk');

    Auth::guard('web')->logout();

    $logout = ActivityLog::query()->where('action', 'auth.logout')->first();
    expect($logout->user_id)->toBe($user->id)->and($logout->actionLabel())->toBe('Keluar');
});

it('does not log a login that failed', function (): void {
    User::factory()->create(['email' => 'budi@spk.test']);

    Livewire::test(Login::class)->fillForm(['email' => 'budi@spk.test', 'password' => 'salah-banget'])->call('authenticate');

    expect(ActivityLog::query()->where('action', 'auth.login')->count())->toBe(0);
});

it('logs a changed assessment value with the old and new value, and nothing when it did not change', function (): void {
    $this->seed(CriteriaSeeder::class);
    $order = ProductionOrder::factory()->create();
    $criterion = Criterion::query()->where('code', 'C3')->first();

    app(AssessmentWriter::class)->setManual($order, $criterion, 60, $this->manager);
    app(AssessmentWriter::class)->setManual($order, $criterion, 80, $this->manager);
    app(AssessmentWriter::class)->setManual($order, $criterion, 80, $this->manager);

    $rows = ActivityLog::query()->where('action', 'assessment.updated')->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[1]->user_id)->toBe($this->manager->id)
        ->and($rows[1]->changeLines())->toBe(['Nilai: 60 menjadi 80'])
        ->and($rows[1]->subject)->toBeInstanceOf(Assessment::class)
        ->and($rows[1]->subjectLabel())->toBe('Penilaian '.$order->number.', C3');
});

it('logs an urgency override with its reason', function (): void {
    $this->seed(CriteriaSeeder::class);
    $order = ProductionOrder::factory()->create();
    $criterion = Criterion::query()->where('code', 'C2')->first();

    app(AssessmentWriter::class)->override($order, $criterion, 5, 'Pelanggan utama minta didahulukan', $this->manager);

    $row = ActivityLog::query()->where('action', 'assessment.updated')->first();

    expect($row->changeLines())->toContain('Alasan: kosong menjadi Pelanggan utama minta didahulukan');
});

it('writes one row with the old and new weight when a weight changes (PRD F14 acceptance)', function (): void {
    $this->seed(CriteriaSeeder::class);
    $criteria = Criterion::query()->active()->get();
    $percent = $criteria->mapWithKeys(fn (Criterion $criterion): array => [$criterion->id => $criterion->weightPercent()])->all();
    $first = $criteria->first();
    $second = $criteria->last();
    $percent[$first->id] += 5;
    $percent[$second->id] -= 5;

    ActivityLog::query()->delete();
    CriterionWeights::setDirect($percent);

    $rows = $first->activities()->where('action', 'criterion.updated')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->changeLines()[0])->toStartWith('Bobot: ')
        ->and($rows[0]->old_values['weight'])->not->toBe($rows[0]->new_values['weight']);
});

it('lets the admin and the leader open the log and keeps it from the manager and the supervisor', function (): void {
    foreach ([$this->admin, $this->leader] as $user) {
        $this->actingAs($user)->get('/admin/log-aktivitas')->assertOk()->assertSee('Log Aktivitas');
    }

    foreach ([$this->manager, $this->supervisor] as $user) {
        $this->actingAs($user)->get('/admin/log-aktivitas')->assertForbidden();
    }
});

it('lists the newest activity first with the user, the kind, the object, and the change', function (): void {
    $order = ProductionOrder::factory()->create(['number' => 'ORD-2026-9001']);
    $this->actingAs($this->manager);
    $order->update(['quantity' => 321]);

    $this->actingAs($this->admin);
    $records = ActivityLog::query()->orderByDesc('id')->get();

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords($records, inOrder: true)
        ->assertSee('Order diubah')
        ->assertSee('Order ORD-2026-9001')
        ->assertSee('Jumlah: ')
        ->assertSee($this->manager->name);
});

it('filters the log by user, kind, and date', function (): void {
    $order = ProductionOrder::factory()->create();
    $this->actingAs($this->manager);
    $order->update(['quantity' => 111]);
    $this->actingAs($this->leader);
    $order->update(['quantity' => 222]);

    $old = ActivityLog::record('setting.updated', null, ['top_n_priority' => 5], ['top_n_priority' => 6], $this->admin);
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->actingAs($this->admin);

    Livewire::test(ListActivityLogs::class)
        ->filterTable('user_id', $this->manager->id)
        ->assertCanSeeTableRecords(ActivityLog::query()->where('user_id', $this->manager->id)->get())
        ->assertCanNotSeeTableRecords(ActivityLog::query()->where('user_id', $this->leader->id)->get());

    Livewire::test(ListActivityLogs::class)
        ->filterTable('action', ['setting.updated'])
        ->assertCanSeeTableRecords([$old])
        ->assertCountTableRecords(1);

    Livewire::test(ListActivityLogs::class)
        ->filterTable('period', ['from' => now()->subDays(2)->toDateString(), 'until' => now()->toDateString()])
        ->assertCanNotSeeTableRecords([$old]);

    Livewire::test(ListActivityLogs::class)
        ->filterTable('period', ['from' => now()->subDays(12)->toDateString(), 'until' => now()->subDays(8)->toDateString()])
        ->assertCanSeeTableRecords([$old])
        ->assertCountTableRecords(1);
});

it('shows a clear message when the log is empty', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ListActivityLogs::class)->assertSee('Belum ada catatan');
});

it('names the kinds of activity in the filter list', function (): void {
    ActivityLog::record('auth.login', null, null, null, $this->admin);
    ActivityLog::record('evaluation.schedule_skipped', null, null, ['reason' => 'x']);

    expect(ActivityLog::actionOptions())->toMatchArray([
        'auth.login' => 'Masuk',
        'evaluation.schedule_skipped' => 'Perhitungan terjadwal dilewati',
    ]);
});

it('cannot be changed or deleted from the page', function (): void {
    $log = ActivityLog::record('auth.login', null, null, null, $this->admin);

    foreach (['update', 'delete'] as $ability) {
        expect($this->admin->can($ability, $log))->toBeFalse()
            ->and($this->leader->can($ability, $log))->toBeFalse();
    }

    expect($this->admin->can('create', ActivityLog::class))->toBeFalse();
});

it('still shows the history of one order to the supervisor', function (): void {
    $order = ProductionOrder::factory()->create();
    $order->update(['quantity' => 99]);

    $this->actingAs($this->supervisor)
        ->get(ProductionOrderResource::getUrl('view', ['record' => $order]))
        ->assertOk();
});

it('logs a changed scale with only the ranges that changed', function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->actingAs($this->manager);
    $c1 = Criterion::query()->where('code', 'C1')->firstOrFail();
    ActivityLog::query()->delete();

    Livewire::test(ScaleSettings::class)
        ->set('data.scales', [
            ['score' => 5, 'label' => null, 'lower_bound' => null, 'upper_bound' => 3],
            ['score' => 4, 'label' => null, 'lower_bound' => 3, 'upper_bound' => 5],
            ['score' => 3, 'label' => null, 'lower_bound' => 5, 'upper_bound' => 10],
            ['score' => 2, 'label' => null, 'lower_bound' => 10, 'upper_bound' => 20],
            ['score' => 1, 'label' => null, 'lower_bound' => 20, 'upper_bound' => null],
        ])
        ->call('save');

    $rows = ActivityLog::query()->where('action', 'criterion.scales_updated')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->subject->is($c1))->toBeTrue()
        ->and($rows[0]->user_id)->toBe($this->manager->id)
        ->and(array_keys($rows[0]->new_values))->not->toBeEmpty();

    Livewire::test(ScaleSettings::class)
        ->set('data.scales', [
            ['score' => 5, 'label' => null, 'lower_bound' => null, 'upper_bound' => 3],
            ['score' => 4, 'label' => null, 'lower_bound' => 3, 'upper_bound' => 5],
            ['score' => 3, 'label' => null, 'lower_bound' => 5, 'upper_bound' => 10],
            ['score' => 2, 'label' => null, 'lower_bound' => 10, 'upper_bound' => 20],
            ['score' => 1, 'label' => null, 'lower_bound' => 20, 'upper_bound' => null],
        ])
        ->call('save');

    expect(ActivityLog::query()->where('action', 'criterion.scales_updated')->count())->toBe(1);
});
