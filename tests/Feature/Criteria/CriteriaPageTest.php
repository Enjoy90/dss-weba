<?php

use App\Enums\CriterionDirection;
use App\Enums\ValueSource;
use App\Filament\Resources\Criteria\Pages\ManageCriteria;
use App\Models\Criterion;
use App\Models\User;
use App\Services\CriterionWeights;
use Database\Seeders\CriteriaSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('opens the criteria page for admin, manager and leader but not for supervisor', function (): void {
    foreach ([User::factory()->admin(), User::factory()->manager(), User::factory()->leader()] as $factory) {
        $this->flushSession();
        $this->actingAs($factory->create())->get('/admin/criteria')->assertOk();
    }

    $this->flushSession();
    $this->actingAs(User::factory()->supervisor()->create())->get('/admin/criteria')->assertForbidden();
});

it('lets manager and leader edit criteria while admin only looks', function (): void {
    $criterion = Criterion::factory()->create();

    foreach ([User::factory()->manager(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        Livewire::test(ManageCriteria::class)
            ->assertActionVisible('create')
            ->assertActionVisible('setWeightsDirectly')
            ->assertActionVisible(TestAction::make('edit')->table($criterion));
    }

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCriteria::class)
        ->assertActionHidden('create')
        ->assertActionHidden('setWeightsDirectly')
        ->assertActionHidden(TestAction::make('edit')->table($criterion))
        ->assertActionHidden(TestAction::make('deactivate')->table($criterion));
});

it('shows the seven starting criteria with their weights and a ready status', function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ManageCriteria::class)
        ->assertCountTableRecords(7)
        ->assertSee('Sisa waktu menuju tenggat')
        ->assertSee('0,2700 (27%)')
        ->assertSee('0,1350 (13,5%)')
        ->assertSee('Siap dihitung')
        ->assertSee('Bobot sedang diatur langsung');
});

it('creates a criterion and recalculates the weights when they follow importance', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $a = Criterion::factory()->importance(3)->create();

    Livewire::test(ManageCriteria::class)
        ->callAction('create', [
            'code' => 'C8',
            'name' => 'Kriteria baru',
            'unit' => 'hari',
            'direction' => CriterionDirection::HigherFirst->value,
            'value_source' => ValueSource::Manual->value,
            'importance' => 3,
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $new = Criterion::query()->where('code', 'C8')->firstOrFail();

    expect((string) $a->fresh()->weight)->toBe('0.5000')
        ->and((string) $new->weight)->toBe('0.5000')
        ->and($new->sort)->toBeGreaterThan(0);
});

it('requires a unique code, a name, a direction and an importance level', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    Criterion::factory()->create(['code' => 'C1']);

    Livewire::test(ManageCriteria::class)
        ->callAction('create', ['code' => 'C1', 'name' => '', 'direction' => null, 'importance' => null])
        ->assertHasFormErrors(['code', 'name', 'direction', 'importance']);
});

it('keeps at least two criteria active', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $first = Criterion::factory()->create();
    $second = Criterion::factory()->create();

    Livewire::test(ManageCriteria::class)
        ->callAction(TestAction::make('deactivate')->table($first));

    expect($first->fresh()->is_active)->toBeTrue();

    Livewire::test(ManageCriteria::class)
        ->callAction(TestAction::make('edit')->table($second), [
            'code' => $second->code,
            'name' => $second->name,
            'direction' => $second->direction->value,
            'value_source' => $second->value_source->value,
            'importance' => $second->importance,
            'is_active' => false,
        ])
        ->assertHasFormErrors(['is_active']);

    expect($second->fresh()->is_active)->toBeTrue();
});

it('deactivates a criterion when more than two are active and activates it again', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $criteria = Criterion::factory()->count(3)->create();

    Livewire::test(ManageCriteria::class)
        ->callAction(TestAction::make('deactivate')->table($criteria[2]));

    expect($criteria[2]->fresh()->is_active)->toBeFalse()
        ->and((string) $criteria[0]->fresh()->weight)->toBe('0.5000');

    Livewire::test(ManageCriteria::class)
        ->callAction(TestAction::make('activate')->table($criteria[2]));

    expect($criteria[2]->fresh()->is_active)->toBeTrue();
});

it('has no way to delete a criterion', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $criterion = Criterion::factory()->create();

    Livewire::test(ManageCriteria::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($criterion));
});

it('saves weights typed directly when they total 100 percent', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $a = Criterion::factory()->create();
    $b = Criterion::factory()->create();

    Livewire::test(ManageCriteria::class)
        ->callAction('setWeightsDirectly', ['w_'.$a->id => 62.5, 'w_'.$b->id => 37.5])
        ->assertHasNoFormErrors();

    expect(CriterionWeights::mode())->toBe('direct')
        ->and((string) $a->fresh()->weight)->toBe('0.6250')
        ->and((string) $b->fresh()->weight)->toBe('0.3750');
});

it('refuses weights typed directly that do not total 100 percent', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $a = Criterion::factory()->create();
    $b = Criterion::factory()->create();
    $before = (string) $a->fresh()->weight;

    Livewire::test(ManageCriteria::class)
        ->callAction('setWeightsDirectly', ['w_'.$a->id => 60, 'w_'.$b->id => 30])
        ->assertNotified('Total bobot belum 100%');

    expect((string) $a->fresh()->weight)->toBe($before)
        ->and(CriterionWeights::mode())->toBe('importance');
});

it('only offers the return to importance weights while weights are set directly', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $a = Criterion::factory()->importance(3)->create();
    $b = Criterion::factory()->importance(1)->create();

    Livewire::test(ManageCriteria::class)
        ->assertActionHidden('useImportanceWeights');

    CriterionWeights::setDirect([$a->id => 50, $b->id => 50]);

    Livewire::test(ManageCriteria::class)
        ->assertActionVisible('useImportanceWeights')
        ->callAction('useImportanceWeights');

    expect(CriterionWeights::mode())->toBe('importance')
        ->and((string) $a->fresh()->weight)->toBe('0.7500');
});

it('warns when the weights are not ready to be calculated', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    Criterion::factory()->create();

    Livewire::test(ManageCriteria::class)
        ->assertSee('minimal dua kriteria');
});
