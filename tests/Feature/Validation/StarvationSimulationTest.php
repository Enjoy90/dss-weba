<?php

use App\Services\Validation\StarvationSimulation;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->simulation = new StarvationSimulation;
});

it('shows the problem without protection: the old order never reaches the priority list in 30 days', function (): void {
    $control = $this->simulation->run(null);

    expect($control['old_first_in_list'])->toBeNull()
        ->and($control['old_done'])->toBeNull()
        ->and($control['overdue_outside_escalation'])->toBeGreaterThan(0)
        ->and($control['longest_overdue_wait'])->toBe(29)
        ->and($control['escalated_orders'])->toBe(0);
});

it('meets target T7 with the wait limit of 7 days (PRD chapter 2)', function (): void {
    $run = $this->simulation->run(7, 7);

    expect($run['overdue_outside_escalation'])->toBe(0)
        ->and($run['old_first_in_list'])->toBe(9)
        ->and($run['old_done'])->toBe(10)
        ->and($run['max_wait'])->toBeLessThanOrEqual(7 + 3);

    // The old order is escalated from day 8, the first day it waits more than 7 days.
    expect($run['daily'][7]['old_group'])->toBe('regular')
        ->and($run['daily'][8]['old_group'])->toBe('escalated');
});

it('meets the target with every limit, and a shorter limit protects earlier but pushes more urgent orders aside', function (): void {
    $five = $this->simulation->run(5, 5);
    $seven = $this->simulation->run(7, 7);
    $ten = $this->simulation->run(10, 10);

    foreach ([$five, $seven, $ten] as $run) {
        expect($run['overdue_outside_escalation'])->toBe(0)->and($run['old_done'])->not->toBeNull();
    }

    expect($five['old_done'])->toBeLessThan($seven['old_done'])
        ->and($seven['old_done'])->toBeLessThan($ten['old_done'])
        ->and($five['urgent_bumped'])->toBeGreaterThan($seven['urgent_bumped'])
        ->and($seven['urgent_bumped'])->toBeGreaterThan($ten['urgent_bumped'])
        ->and($five['average_wait'])->toBe($ten['average_wait']);
});

it('gives the same result every time it runs', function (): void {
    expect($this->simulation->run(7, 7))->toBe((new StarvationSimulation)->run(7, 7));
});

it('prints the comparison table and reports the target', function (): void {
    $exit = Artisan::call('validation:simulate-starvation');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Tanpa perlindungan')->toContain('Batas 7 hari')->toContain('Sasaran T7 tercapai');
});
