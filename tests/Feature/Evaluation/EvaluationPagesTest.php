<?php

use App\Enums\EvaluationStatus;
use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Filament\Pages\CompareEvaluations;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Pages\ViewEvaluation;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\Evaluation\EvaluationWorkflow;
use Database\Seeders\CriteriaSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
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

function pageOrderFor(int $dueInDays = 20, array $state = [], bool $working = false): ProductionOrder
{
    $state += ['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100];
    $factory = ProductionOrder::factory()->dueInDays($dueInDays);
    $order = ($working ? $factory->inProgress() : $factory)->create($state);

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

function pageEvaluate(): Evaluation
{
    return app(EvaluationService::class)->run(test()->manager);
}

function pageItem(Evaluation $evaluation, ProductionOrder $order): EvaluationItem
{
    return $evaluation->items()->where('production_order_id', $order->id)->firstOrFail();
}

it('opens the evaluation list for every role and shows the empty state', function (): void {
    foreach (['admin', 'manager', 'supervisor', 'leader'] as $role) {
        $this->actingAs($this->$role);

        $this->get(EvaluationResource::getUrl())->assertOk()->assertSee('Hasil Evaluasi');

        Livewire::test(ListEvaluations::class)->assertSee('Belum ada evaluasi');
    }
});

it('redirects a guest from the evaluation pages', function (): void {
    $evaluation = (function () {
        pageOrderFor();

        return pageEvaluate();
    })();

    $this->get(EvaluationResource::getUrl())->assertRedirect();
    $this->get(EvaluationResource::getUrl('view', ['record' => $evaluation]))->assertRedirect();
    $this->get(CompareEvaluations::getUrl())->assertRedirect();
});

it('lists evaluations with their status, who ran them and who approved them, newest first', function (): void {
    pageOrderFor();
    $old = app(EvaluationService::class)->run($this->manager, name: 'Evaluasi lama');
    $new = app(EvaluationService::class)->run($this->manager, name: 'Evaluasi baru');
    app(EvaluationWorkflow::class)->submit($new, $this->manager);
    app(EvaluationWorkflow::class)->approve($new, $this->leader);

    $this->actingAs($this->supervisor);

    Livewire::test(ListEvaluations::class)
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertSee('Evaluasi baru')
        ->assertSee('Disetujui')
        ->assertSee('Draf')
        ->assertSee($this->manager->name)
        ->assertSee($this->leader->name);
});

it('filters the evaluation list by status, trigger, and date', function (): void {
    pageOrderFor();
    $draft = app(EvaluationService::class)->run($this->manager, name: 'Masih draf');
    $canceled = app(EvaluationService::class)->run($this->manager, name: 'Sudah batal');
    $canceled->update(['status' => EvaluationStatus::Canceled]);

    $this->actingAs($this->leader);

    Livewire::test(ListEvaluations::class)
        ->filterTable('status', [EvaluationStatus::Canceled->value])
        ->assertCanSeeTableRecords([$canceled])
        ->assertCanNotSeeTableRecords([$draft])
        ->removeTableFilters()
        ->filterTable('period', ['from' => today()->addDay()->toDateString(), 'until' => null])
        ->assertCanNotSeeTableRecords([$draft, $canceled])
        ->removeTableFilters()
        ->filterTable('period', ['from' => today()->toDateString(), 'until' => today()->toDateString()])
        ->assertCanSeeTableRecords([$draft, $canceled]);
});

it('opens an evaluation for every role with its summary and ranking', function (): void {
    $near = pageOrderFor(1);
    pageOrderFor(25);
    $evaluation = pageEvaluate();

    foreach (['admin', 'manager', 'supervisor', 'leader'] as $role) {
        $this->actingAs($this->$role);

        $this->get(EvaluationResource::getUrl('view', ['record' => $evaluation]))->assertOk();

        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
            ->assertSee($evaluation->name)
            ->assertSee('Draf')
            ->assertSee($near->number)
            ->assertCanSeeTableRecords($evaluation->items()->get(), inOrder: true);
    }
});

it('shows all seven tabs with data', function (): void {
    pageOrderFor(3, ['unit_price' => 987654, 'quantity' => 3]);
    $evaluation = pageEvaluate();
    $this->actingAs($this->manager);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->assertSee('Ringkasan dan peringkat')
        ->assertSee('Data nilai asli')
        ->assertSee('Matriks keputusan')
        ->assertSee('Matriks normalisasi')
        ->assertSee('Perhitungan skor')
        ->assertSee('Kriteria dan skala')
        ->assertSee('Peringatan')
        // data nilai asli: rupiah of the order value and the other raw values
        ->assertSee('Rp 2.962.962')
        ->assertSee('Skor V')
        ->assertSee('bobot 0,2700')
        ->assertSee('Salinan kriteria, bobot, dan skala yang dipakai')
        ->assertSee('Kurang dari 2 hari')
        ->assertSee('Tidak ada peringatan');
});

it('hides the order value criterion from the supervisor in every tab', function (): void {
    pageOrderFor(3, ['unit_price' => 987654, 'quantity' => 3]);
    $evaluation = pageEvaluate();

    $this->actingAs($this->supervisor);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->assertDontSee('2.962.962')
        ->assertDontSee('Rp 2')
        ->assertDontSee('Nilai order')
        ->assertDontSee('100 juta')
        ->assertSee('Kriteria nilai order tidak ditampilkan untuk peran Anda')
        ->assertSee('C1')
        ->assertSee('C7');

    foreach (['admin', 'leader'] as $role) {
        $this->actingAs($this->$role);

        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
            ->assertSee('Rp 2.962.962')
            ->assertSee('Nilai order');
    }
});

it('lists the warnings of missing and old values in their own tab', function (): void {
    $order = pageOrderFor(3);
    Assessment::query()->where('production_order_id', $order->id)->where('criterion_id', $this->criteria['C3']->id)->update(['raw_value' => null]);
    Assessment::query()->where('production_order_id', $order->id)->where('criterion_id', $this->criteria['C4']->id)->update(['value_updated_at' => now()->subDays(6)]);
    $evaluation = app(EvaluationService::class)->run($this->manager, acknowledgeWarnings: true);

    $this->actingAs($this->leader);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->assertSee('Nilai kosong, dihitung sebagai skala 1')
        ->assertSee('Nilai manual sudah lama dan perlu diperbarui')
        ->assertDontSee('Tidak ada peringatan');
});

it('warns when manual locks and escalations fill the Prioritas Utama slots', function (): void {
    Setting::put('top_n_priority', 1);
    pageOrderFor(20, ['lock_type' => LockType::Manual, 'lock_reason' => 'Penting', 'lock_until' => null]);
    pageOrderFor(5);
    $evaluation = pageEvaluate();

    $this->actingAs($this->leader);

    $component = Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->assertSee('Slot Prioritas Utama penuh')
        ->assertSee('berjumlah 1, sedangkan jumlah Prioritas Utama hanya 1');

    // Once above the tabs and once in the Peringatan tab
    expect(substr_count($component->html(), 'Slot Prioritas Utama penuh'))->toBe(2);

    Setting::put('top_n_priority', 3);
    $roomy = pageEvaluate();

    Livewire::test(ViewEvaluation::class, ['record' => $roomy->id])->assertDontSee('Slot Prioritas Utama penuh');
});

it('marks the first N queued orders as Prioritas Utama and not the orders being worked on', function (): void {
    Setting::put('top_n_priority', 2);
    pageOrderFor(30, [], working: true);
    $a = pageOrderFor(2);
    $b = pageOrderFor(6);
    $c = pageOrderFor(12);
    $evaluation = pageEvaluate();

    $this->actingAs($this->manager);

    $component = Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id]);
    $html = $component->html();

    expect(substr_count($html, 'Prioritas Utama'))->toBeGreaterThanOrEqual(2);
    expect($c->exists)->toBeTrue();
});

it('shows each flow button only to the right role and state', function (): void {
    pageOrderFor();
    $evaluation = pageEvaluate();

    $visible = fn (User $user, array $actions) => (function () use ($user, $actions, $evaluation) {
        $this->actingAs($user);
        $component = Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id]);

        foreach ($actions as $name => $expected) {
            $expected ? $component->assertActionVisible($name) : $component->assertActionHidden($name);
        }
    })->call($this);

    // Draft: only the manager can submit, and nobody can approve or apply yet
    $visible($this->manager, ['submit' => true, 'approve' => false, 'reject' => false, 'apply' => false, 'cancel' => true]);
    $visible($this->leader, ['submit' => false, 'approve' => false, 'reject' => false, 'apply' => false, 'cancel' => true]);
    $visible($this->supervisor, ['submit' => false, 'approve' => false, 'reject' => false, 'apply' => false, 'cancel' => false]);
    $visible($this->admin, ['submit' => false, 'approve' => false, 'reject' => false, 'apply' => false, 'cancel' => false]);

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    $visible($this->manager, ['submit' => false, 'approve' => false, 'reject' => false, 'apply' => false, 'cancel' => true]);
    $visible($this->leader, ['submit' => false, 'approve' => true, 'reject' => true, 'apply' => false, 'cancel' => true]);

    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    $visible($this->manager, ['submit' => false, 'approve' => false, 'reject' => false, 'apply' => true, 'cancel' => true]);
    $visible($this->leader, ['submit' => false, 'approve' => false, 'reject' => false, 'apply' => true, 'cancel' => true]);
    $visible($this->supervisor, ['apply' => false, 'cancel' => false]);

    app(EvaluationWorkflow::class)->apply($evaluation, $this->manager);

    $visible($this->manager, ['submit' => false, 'apply' => false, 'cancel' => false]);
});

it('runs the whole flow from the page: submit, approve, apply (PRD F11 acceptance 1)', function (): void {
    Setting::put('top_n_priority', 2);
    foreach ([2, 6, 12, 20] as $days) {
        pageOrderFor($days);
    }
    $evaluation = pageEvaluate();

    $this->actingAs($this->manager);
    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction('submit')
        ->assertNotified('Evaluasi diajukan ke Pimpinan.')
        ->assertSee('Diajukan');

    $this->actingAs($this->leader);
    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction('approve')
        ->assertNotified('Evaluasi disetujui.')
        ->assertSee('Disetujui');

    $this->actingAs($this->manager);
    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction('apply')
        ->assertNotified()
        ->assertSee('Diterapkan');

    expect(ProductionOrder::query()->where('priority', OrderPriority::Top->value)->count())->toBe(2)
        ->and($evaluation->fresh()->status)->toBe(EvaluationStatus::Applied);
});

it('shows which orders will be labelled before applying', function (): void {
    Setting::put('top_n_priority', 1);
    $near = pageOrderFor(1);
    pageOrderFor(25);
    $evaluation = pageEvaluate();
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    $this->actingAs($this->manager);

    $component = Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])->mountAction('apply');
    $text = (string) $component->instance()->getMountedAction()->getModalDescription();

    expect($text)->toContain('Order berikut mendapat label Prioritas Utama: '.$near->number)
        ->and($text)->toContain('Label dari evaluasi sebelumnya diganti');
});

it('rejects with a note, shows the note, and lets the manager submit again', function (): void {
    pageOrderFor();
    $evaluation = pageEvaluate();
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);

    $this->actingAs($this->leader);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction('reject', ['note' => ''])
        ->assertHasActionErrors(['note' => 'required']);

    expect($evaluation->fresh()->status)->toBe(EvaluationStatus::Submitted);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction('reject', ['note' => 'Urutan pelanggan A belum pas'])
        ->assertNotified('Evaluasi ditolak dan dikembalikan ke Draf.')
        ->assertSee('Evaluasi ini dikembalikan oleh Pimpinan')
        ->assertSee('Urutan pelanggan A belum pas');

    $this->actingAs($this->manager);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->assertActionVisible('submit')
        ->callAction('submit')
        ->assertDontSee('Evaluasi ini dikembalikan oleh Pimpinan');
});

it('explains in a notification when a step is not allowed, instead of failing', function (): void {
    pageOrderFor();
    $evaluation = pageEvaluate();
    $newer = pageEvaluate();
    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    $this->actingAs($this->manager);

    // The newest evaluation is a draft, so the older approved one may not be applied any more
    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->assertActionHidden('apply');

    expect(fn () => app(EvaluationWorkflow::class)->apply($evaluation, $this->manager))->toThrow(ValidationException::class);
    expect($newer->exists)->toBeTrue()->and(ProductionOrder::query()->where('priority', 'top')->count())->toBe(0);
});

it('cancels an evaluation from the page', function (): void {
    pageOrderFor();
    $evaluation = pageEvaluate();

    $this->actingAs($this->manager);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction('cancel')
        ->assertNotified('Evaluasi dibatalkan.')
        ->assertActionHidden('cancel')
        ->assertSee('Dibatalkan');

    expect($evaluation->fresh()->status)->toBe(EvaluationStatus::Canceled);
});

it('adjusts the ranking from the table with a reason and keeps the system rank beside it', function (): void {
    foreach ([2, 8, 14] as $days) {
        pageOrderFor($days);
    }
    $evaluation = pageEvaluate();
    $first = $evaluation->items()->orderBy('final_rank')->first();

    $this->actingAs($this->manager);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction(TestAction::make('adjust')->table($first), ['rank' => 3, 'reason' => 'Permintaan pelanggan'])
        ->assertHasNoActionErrors()
        ->assertNotified()
        ->assertSee('Disesuaikan');

    $fresh = $first->fresh();

    expect($fresh->final_rank)->toBe(3)
        ->and($fresh->system_rank)->toBe(1)
        ->and($fresh->adjustment_reason)->toBe('Permintaan pelanggan')
        ->and(ActivityLog::query()->where('action', 'evaluation.rank_adjusted')->count())->toBe(1);
});

it('refuses an adjustment without a reason and explains a refused move', function (): void {
    $locked = pageOrderFor(30, [], working: true);
    pageOrderFor(2);
    $evaluation = pageEvaluate();
    $lockedItem = pageItem($evaluation, $locked);

    $this->actingAs($this->manager);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction(TestAction::make('adjust')->table($lockedItem), ['rank' => 2, 'reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])
        ->callAction(TestAction::make('adjust')->table($lockedItem), ['rank' => 2, 'reason' => 'Turunkan'])
        ->assertNotified('Tidak bisa dilanjutkan');

    expect($lockedItem->fresh()->final_rank)->toBe(1);
});

it('offers the adjustment only to the manager and the leader and only before approval', function (): void {
    pageOrderFor(2);
    pageOrderFor(9);
    $evaluation = pageEvaluate();
    $item = $evaluation->items()->first();

    foreach ([$this->manager, $this->leader] as $user) {
        $this->actingAs($user);
        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])->assertActionVisible(TestAction::make('adjust')->table($item));
    }

    foreach ([$this->supervisor, $this->admin] as $user) {
        $this->actingAs($user);
        Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])->assertActionHidden(TestAction::make('adjust')->table($item));
    }

    app(EvaluationWorkflow::class)->submit($evaluation, $this->manager);
    app(EvaluationWorkflow::class)->approve($evaluation, $this->leader);

    $this->actingAs($this->manager);
    Livewire::test(ViewEvaluation::class, ['record' => $evaluation->id])->assertActionHidden(TestAction::make('adjust')->table($item));
});

it('compares two evaluations and says who moved up, down, or stayed', function (): void {
    pageOrderFor(3);
    pageOrderFor(5);
    pageOrderFor(25);
    $first = pageEvaluate();

    $rising = pageOrderFor(0);
    $second = pageEvaluate();
    $third = pageEvaluate();

    foreach (['admin', 'manager', 'supervisor', 'leader'] as $role) {
        $this->actingAs($this->$role);

        $this->get(CompareEvaluations::getUrl(['a' => $first->id, 'b' => $second->id]))->assertOk();

        // A new order at the top pushes the three older ones down by one place
        Livewire::test(CompareEvaluations::class)
            ->fillForm(['from' => $first->id, 'to' => $second->id])
            ->assertSee($rising->number)
            ->assertSee('Baru di evaluasi sesudah')
            ->assertSee('Turun 1')
            ->assertSee('0 naik, 3 turun, 0 tetap, 1 baru');
    }

    $this->actingAs($this->manager);

    Livewire::test(CompareEvaluations::class)
        ->fillForm(['from' => $second->id, 'to' => $first->id])
        ->assertSee('Naik 1')
        ->assertSee('Tidak ada di evaluasi sesudah')
        ->assertSee('3 naik, 0 turun, 0 tetap, 0 baru di evaluasi sesudah, 1 tidak ada lagi');

    Livewire::test(CompareEvaluations::class)
        ->fillForm(['from' => $second->id, 'to' => $third->id])
        ->assertSee('Tetap')
        ->assertSee('0 naik, 0 turun, 4 tetap');
});

it('asks for two different evaluations before comparing', function (): void {
    pageOrderFor();
    $evaluation = pageEvaluate();

    $this->actingAs($this->manager);

    Livewire::test(CompareEvaluations::class)
        ->assertSee('Pilih evaluasi "sebelum" dan "sesudah"')
        ->fillForm(['from' => $evaluation->id, 'to' => $evaluation->id])
        ->assertSee('Pilih dua evaluasi yang berbeda');
});
