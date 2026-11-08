<?php

use App\Filament\Pages\ScaleSettings;
use App\Models\Criterion;
use App\Models\User;
use Database\Seeders\CriteriaSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(CriteriaSeeder::class);
});

function scaleRowsFor(array $triples): array
{
    return array_map(fn (array $t): array => [
        'score' => $t[0],
        'label' => null,
        'lower_bound' => $t[1],
        'upper_bound' => $t[2],
    ], $triples);
}

it('opens the scale page for admin, manager and leader but not for supervisor', function (): void {
    foreach ([User::factory()->admin(), User::factory()->manager(), User::factory()->leader()] as $factory) {
        $this->flushSession();
        $this->actingAs($factory->create())->get('/admin/skala-nilai')->assertOk();
    }

    $this->flushSession();
    $this->actingAs(User::factory()->supervisor()->create())->get('/admin/skala-nilai')->assertForbidden();
});

it('starts with the first criterion and its five saved ranges', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    $component = Livewire::test(ScaleSettings::class)
        ->assertSee('Makin kecil didahulukan');

    $rows = collect($component->get('data.scales'));

    expect($rows->pluck('score')->all())->toBe([5, 4, 3, 2, 1])
        ->and($rows->pluck('label')->all())->toContain('Kurang dari 2 hari', '15 hari ke atas')
        ->and($rows->first()['upper_bound'])->toEqual('2.00');
});

it('loads the ranges of another criterion when it is chosen', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $c7 = Criterion::query()->where('code', 'C7')->firstOrFail();

    $component = Livewire::test(ScaleSettings::class)
        ->set('data.criterion_id', $c7->id)
        ->assertSee('Makin besar didahulukan');

    expect(collect($component->get('data.scales'))->pluck('label')->all())->toContain('7 hari ke atas');
});

it('saves ranges that are valid', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $c1 = Criterion::query()->where('code', 'C1')->firstOrFail();

    Livewire::test(ScaleSettings::class)
        ->set('data.scales', scaleRowsFor([[5, null, 3], [4, 3, 5], [3, 5, 10], [2, 10, 20], [1, 20, null]]))
        ->call('save');

    $saved = $c1->scales()->get()->keyBy('score');

    expect((float) $saved[5]->upper_bound)->toBe(3.0)
        ->and((float) $saved[1]->lower_bound)->toBe(20.0)
        ->and($c1->fresh()->scoreFor(4))->toBe(4)
        ->and($c1->fresh()->scoreFor(3))->toBe(4);
});

it('refuses overlapping ranges and keeps the saved ones', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $c1 = Criterion::query()->where('code', 'C1')->firstOrFail();

    Livewire::test(ScaleSettings::class)
        ->set('data.scales', scaleRowsFor([[5, null, 3], [4, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]))
        ->call('save')
        ->assertNotified('Rentang skala belum benar');

    expect((float) $c1->scales()->where('score', 5)->value('upper_bound'))->toBe(2.0);
});

it('refuses a scale that runs against the criterion direction', function (): void {
    $this->actingAs(User::factory()->manager()->create());
    $c1 = Criterion::query()->where('code', 'C1')->firstOrFail();

    Livewire::test(ScaleSettings::class)
        ->set('data.scales', scaleRowsFor([[1, null, 2], [2, 2, 4], [3, 4, 8], [4, 8, 15], [5, 15, null]]))
        ->call('save')
        ->assertNotified('Rentang skala belum benar');

    expect((float) $c1->scales()->where('score', 5)->value('upper_bound'))->toBe(2.0);
});

it('shows the scale that a sample value would get before saving', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ScaleSettings::class)
        ->set('data.sample', '0')
        ->assertSee('menjadi skala 5')
        ->set('data.sample', '2')
        ->assertSee('menjadi skala 4')
        ->set('data.sample', '20')
        ->assertSee('menjadi skala 1');
});

it('tells the user to fix the ranges first when testing against an invalid draft', function (): void {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ScaleSettings::class)
        ->set('data.scales', scaleRowsFor([[5, null, 3], [4, 2, 4], [3, 4, 8], [2, 8, 15], [1, 15, null]]))
        ->set('data.sample', '1')
        ->assertSee('Rentang belum benar');
});

it('lets admin and leader view the scale but not save it', function (): void {
    foreach ([User::factory()->admin(), User::factory()->leader()] as $factory) {
        $this->actingAs($factory->create());

        $component = Livewire::test(ScaleSettings::class)
            ->assertDontSee('Simpan skala');

        expect(collect($component->get('data.scales'))->pluck('label')->all())->toContain('Kurang dari 2 hari');

        $component->call('save')->assertForbidden();
    }
});
