<?php

use App\Enums\EvaluationStatus;
use App\Enums\OrderStatus;
use App\Filament\Pages\SystemSettingsPage;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssessmentResolver;
use App\Services\Dashboard\DashboardFigures;
use App\Services\Evaluation\EvaluationService;
use App\Services\Report\CompanyProfile;
use App\Services\Settings\SystemSettings;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->leader = User::factory()->leader()->create();
});

function settingsSeedOrders(int $count = 3): void
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

function settingsInvalid(callable $call, string $field): void
{
    try {
        $call();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    throw new RuntimeException('Diharapkan ValidationException pada '.$field);
}

it('gives every setting its default when nothing is saved', function (): void {
    $values = SystemSettings::values();

    expect($values['top_n_priority'])->toBe(5)
        ->and($values['approval_required'])->toBeTrue()
        ->and($values['stale_value_days'])->toBe(3)
        ->and($values['incomplete_orders'])->toBe('worst')
        ->and($values['lock_in_progress'])->toBeTrue()
        ->and($values['max_wait_days'])->toBe(7)
        ->and($values['held_after_evaluations'])->toBe(5)
        ->and($values['held_min_days'])->toBe(4)
        ->and($values['schedule_enabled'])->toBeTrue()
        ->and($values['schedule_time'])->toBe('06:00')
        ->and($values['near_deadline_days'])->toBe(3)
        ->and($values['company_name'])->toBe('PT Weba International');
});

it('lets the admin save every setting and logs one row with the old and new values', function (): void {
    $changes = SystemSettings::save([
        'top_n_priority' => 7,
        'approval_required' => false,
        'schedule_time' => '07:30',
        'company_name' => 'PT Contoh Sepatu',
    ], $this->admin);

    expect(array_keys($changes))->toEqualCanonicalizing(['top_n_priority', 'approval_required', 'schedule_time', 'company_name'])
        ->and(SystemSettings::values()['top_n_priority'])->toBe(7)
        ->and(SystemSettings::values()['approval_required'])->toBeFalse()
        ->and(SystemSettings::scheduleTime())->toBe('07:30');

    $log = ActivityLog::query()->where('action', 'setting.updated')->get();

    expect($log)->toHaveCount(1)
        ->and($log[0]->user_id)->toBe($this->admin->id)
        ->and($log[0]->old_values['top_n_priority'])->toBe(5)
        ->and($log[0]->new_values['top_n_priority'])->toBe(7)
        ->and($log[0]->changeLines())->toContain('Jumlah Prioritas Utama: 5 menjadi 7')
        ->and($log[0]->changeLines())->toContain('Persetujuan Pimpinan wajib: ya menjadi tidak');
});

it('writes no log row when nothing changed', function (): void {
    expect(SystemSettings::save(['top_n_priority' => 5, 'approval_required' => true], $this->admin))->toBe([])
        ->and(ActivityLog::query()->where('action', 'setting.updated')->count())->toBe(0);

    SystemSettings::save(['top_n_priority' => 6], $this->admin);
    SystemSettings::save(['top_n_priority' => 6], $this->admin);

    expect(ActivityLog::query()->where('action', 'setting.updated')->count())->toBe(1);
});

it('lets the leader change the decision rules but not the company profile or the schedule', function (): void {
    SystemSettings::save(['top_n_priority' => 8, 'max_wait_days' => 10, 'incomplete_orders' => 'exclude'], $this->leader);

    expect(SystemSettings::values()['top_n_priority'])->toBe(8)
        ->and(SystemSettings::values()['max_wait_days'])->toBe(10);

    foreach (['company_name' => 'Nama baru', 'schedule_time' => '08:00', 'schedule_enabled' => false, 'near_deadline_days' => 5] as $key => $value) {
        expect(fn () => SystemSettings::save([$key => $value], $this->leader))->toThrow(AuthorizationException::class);
    }

    expect(SystemSettings::values()['company_name'])->toBe('PT Weba International')
        ->and(SystemSettings::scheduleTime())->toBe('06:00');
});

it('refuses the manager and the supervisor', function (): void {
    foreach ([$this->manager, $this->supervisor] as $user) {
        expect(fn () => SystemSettings::save(['top_n_priority' => 9], $user))->toThrow(AuthorizationException::class);
    }

    expect(SystemSettings::values()['top_n_priority'])->toBe(5)
        ->and(ActivityLog::query()->where('action', 'setting.updated')->count())->toBe(0);
});

it('refuses values outside their range', function (): void {
    foreach ([
        ['top_n_priority', 0], ['top_n_priority', 51], ['top_n_priority', 'abc'],
        ['stale_value_days', -1], ['stale_value_days', 31],
        ['incomplete_orders', 'mungkin'],
        ['max_wait_days', 0], ['max_wait_days', 61],
        ['held_after_evaluations', 0], ['held_after_evaluations', 21],
        ['schedule_time', '25:00'], ['schedule_time', '6'], ['schedule_time', ''],
        ['near_deadline_days', 0], ['near_deadline_days', 15],
        ['company_name', str_repeat('a', 121)],
    ] as [$key, $value]) {
        settingsInvalid(fn () => SystemSettings::save([$key => $value], $this->admin), $key);
    }

    expect(ActivityLog::query()->where('action', 'setting.updated')->count())->toBe(0);
});

it('keeps the time of the held warning shorter than the wait limit', function (): void {
    settingsInvalid(fn () => SystemSettings::save(['held_min_days' => 7], $this->admin), 'held_min_days');
    settingsInvalid(fn () => SystemSettings::save(['max_wait_days' => 3], $this->admin), 'held_min_days');

    SystemSettings::save(['max_wait_days' => 10, 'held_min_days' => 9], $this->admin);

    expect(SystemSettings::values()['held_min_days'])->toBe(9);
});

it('uses the number of priority orders in the next calculation', function (): void {
    settingsSeedOrders(4);
    SystemSettings::save(['top_n_priority' => 2], $this->admin);

    $evaluation = app(EvaluationService::class)->run($this->manager);

    expect($evaluation->top_n)->toBe(2)
        ->and($evaluation->settings_snapshot['top_n_priority'])->toBe(2);

    SystemSettings::save(['top_n_priority' => 3], $this->admin);

    expect($evaluation->fresh()->top_n)->toBe(2);
});

it('turns the approval step off and on', function (): void {
    settingsSeedOrders();
    $evaluation = app(EvaluationService::class)->run($this->manager);

    expect($evaluation->allowsSubmit())->toBeTrue()->and($evaluation->allowsApply())->toBeFalse();

    SystemSettings::save(['approval_required' => false], $this->leader);

    expect($evaluation->allowsSubmit())->toBeFalse()->and($evaluation->allowsApply())->toBeTrue();
});

it('uses the days for outdated manual values', function (): void {
    settingsSeedOrders(1);
    $assessment = Assessment::query()->first();
    $assessment->forceFill(['value_updated_at' => now()->subDays(5)])->save();

    expect(AssessmentResolver::make()->resolveAll(ProductionOrder::query()->first()->load(['customer', 'assessments']), Criterion::query()->active()->with('scales')->get())->contains(fn ($value) => $value->stale))->toBeTrue();

    SystemSettings::save(['stale_value_days' => 10], $this->admin);

    expect(AssessmentResolver::make()->resolveAll(ProductionOrder::query()->first()->load(['customer', 'assessments']), Criterion::query()->active()->with('scales')->get())->contains(fn ($value) => $value->stale))->toBeFalse();
});

it('uses the wait limit for the escalation group', function (): void {
    settingsSeedOrders(3);
    ProductionOrder::query()->orderBy('id')->first()->forceFill(['order_date' => today()->subDays(9)])->saveQuietly();

    $first = app(EvaluationService::class)->run($this->manager);
    expect($first->items()->where('rank_group', 'escalated')->count())->toBe(1);

    SystemSettings::save(['max_wait_days' => 12, 'held_min_days' => 4], $this->admin);
    $second = app(EvaluationService::class)->run($this->manager);

    expect($second->items()->where('rank_group', 'escalated')->count())->toBe(0);
});

it('leaves the automatic lock off when the setting is off', function (): void {
    SystemSettings::save(['lock_in_progress' => false], $this->admin);

    $order = ProductionOrder::factory()->create();
    $order->update(['status' => OrderStatus::InProgress]);

    expect($order->fresh()->lock_type->value)->toBe('none');
});

it('uses the days for near the deadline in the list, the card, and the label', function (): void {
    ProductionOrder::factory()->dueInDays(5)->create();
    ProductionOrder::factory()->dueInDays(2)->create();

    expect(ProductionOrder::query()->nearDeadline()->count())->toBe(1);

    SystemSettings::save(['near_deadline_days' => 6], $this->admin);

    expect(ProductionOrder::query()->nearDeadline()->count())->toBe(2)
        ->and(app(DashboardFigures::class)->counts()['near'])->toBe(2);
});

it('can leave incomplete orders out of the calculation', function (): void {
    settingsSeedOrders(3);
    ProductionOrder::factory()->dueInDays(10)->create();

    $withWorst = app(EvaluationService::class)->preflight();

    expect($withWorst->orderCount())->toBe(4)->and($withWorst->incomplete)->toHaveCount(1)->and($withWorst->excluded)->toBe([]);

    SystemSettings::save(['incomplete_orders' => 'exclude'], $this->leader);
    $preflight = app(EvaluationService::class)->preflight();

    expect($preflight->orderCount())->toBe(3)
        ->and($preflight->incomplete)->toBe([])
        ->and($preflight->excluded)->toHaveCount(1)
        ->and($preflight->hasWarnings())->toBeFalse();

    $evaluation = app(EvaluationService::class)->run($this->manager);

    expect($evaluation->order_count)->toBe(3)
        ->and($evaluation->items()->count())->toBe(3)
        ->and($evaluation->settings_snapshot['incomplete_orders'])->toBe('exclude')
        ->and($evaluation->status)->toBe(EvaluationStatus::Draft);
});

it('blocks the calculation when every order is incomplete and left out', function (): void {
    test()->seed(CriteriaSeeder::class);
    ProductionOrder::factory()->dueInDays(10)->create();
    SystemSettings::save(['incomplete_orders' => 'exclude'], $this->admin);

    $preflight = app(EvaluationService::class)->preflight();

    expect($preflight->canRun())->toBeFalse()
        ->and($preflight->blockers[0])->toContain('belum lengkap dan dikecualikan');
});

it('reads the letterhead from the saved company profile and removes the old logo when it is replaced', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('logos/lama.png', 'x');
    Storage::disk('public')->put('logos/baru.png', 'y');

    SystemSettings::save(['company_name' => 'PT Contoh Sepatu', 'company_address' => 'Jl. Industri 5', 'company_phone' => '031-555', 'company_logo' => 'logos/lama.png'], $this->admin);

    expect(CompanyProfile::load()->name)->toBe('PT Contoh Sepatu')
        ->and(CompanyProfile::load()->lines())->toBe(['Jl. Industri 5', 'Telepon 031-555'])
        ->and(CompanyProfile::load()->logoPath)->not->toBeNull();

    SystemSettings::save(['company_logo' => 'logos/baru.png'], $this->admin);

    expect(Storage::disk('public')->exists('logos/lama.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('logos/baru.png'))->toBeTrue();
});

it('falls back to the default name when the company name is cleared', function (): void {
    SystemSettings::save(['company_name' => 'PT Contoh Sepatu'], $this->admin);
    SystemSettings::save(['company_name' => '   '], $this->admin);

    expect(CompanyProfile::load()->name)->toBe('PT Weba International');
});

it('opens the settings page for the admin and the leader only', function (): void {
    foreach ([$this->admin, $this->leader] as $user) {
        $this->actingAs($user)->get('/admin/pengaturan')->assertOk()->assertSee('Aturan keputusan');
    }

    foreach ([$this->manager, $this->supervisor] as $user) {
        $this->actingAs($user)->get('/admin/pengaturan')->assertForbidden();
    }
});

it('shows every section to the admin and only the decision rules to the leader', function (): void {
    $this->actingAs($this->admin);
    Livewire::test(SystemSettingsPage::class)
        ->assertSee('Profil perusahaan')->assertSee('Aturan keputusan')->assertSee('Perlindungan order lama')->assertSee('Evaluasi terjadwal')->assertSee('Tampilan');

    $this->actingAs($this->leader);
    Livewire::test(SystemSettingsPage::class)
        ->assertSee('Aturan keputusan')->assertSee('Perlindungan order lama')
        ->assertDontSee('Profil perusahaan')->assertDontSee('Evaluasi terjadwal')->assertDontSee('Jam pelaksanaan');
});

it('saves from the page and tells what happened', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(SystemSettingsPage::class)
        ->fillForm(['top_n_priority' => 9, 'near_deadline_days' => 4])
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('Pengaturan disimpan');

    expect(SystemSettings::values()['top_n_priority'])->toBe(9)
        ->and(SystemSettings::values()['near_deadline_days'])->toBe(4);

    Livewire::test(SystemSettingsPage::class)->call('save')->assertNotified('Tidak ada perubahan untuk disimpan');
});

it('shows the rule that was broken next to the field', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(SystemSettingsPage::class)
        ->fillForm(['held_min_days' => 8])
        ->call('save')
        ->assertHasErrors(['data.held_min_days']);

    expect(SystemSettings::values()['held_min_days'])->toBe(4);
});

it('saves from the page as the leader without touching the admin settings', function (): void {
    Setting::put('company_name', 'PT Contoh Sepatu');
    $this->actingAs($this->leader);

    Livewire::test(SystemSettingsPage::class)
        ->fillForm(['top_n_priority' => 6])
        ->call('save')
        ->assertNotified('Pengaturan disimpan');

    expect(SystemSettings::values()['top_n_priority'])->toBe(6)
        ->and(SystemSettings::values()['company_name'])->toBe('PT Contoh Sepatu');
});

it('puts the settings page in the Pengaturan group for the admin and the leader', function (): void {
    expect(SystemSettingsPage::getNavigationGroup())->toBe('Pengaturan');

    $this->actingAs($this->leader)->get('/admin')->assertSee('Pengaturan')->assertSee('Log Aktivitas');
});

it('keeps the log and settings out of the menu of the manager', function (): void {
    $this->actingAs($this->manager)->get('/admin')->assertDontSee('Log Aktivitas')->assertDontSee('log-aktivitas');
});
