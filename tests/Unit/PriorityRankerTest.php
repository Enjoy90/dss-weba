<?php

use App\Enums\RankGroup;
use App\Services\Saw\PriorityRanker;
use App\Services\Saw\RankCandidate;
use App\Services\Saw\SawCalculator;
use Carbon\CarbonImmutable;

function candidate(float $score, int $waiting = 0, bool $locked = false, bool $queued = true, int $deadlineIn = 20, int $enteredAgo = 0): RankCandidate
{
    $today = CarbonImmutable::parse('2026-11-09');

    return new RankCandidate($score, $today->addDays($deadlineIn), $today->subDays($enteredAgo), $waiting, $queued, $locked);
}

function finalOrder(array $placements): array
{
    uasort($placements, fn ($a, $b) => $a->finalRank <=> $b->finalRank);

    return array_keys($placements);
}

function weights117(): array
{
    return ['C1' => 0.27, 'C2' => 0.18, 'C3' => 0.135, 'C4' => 0.135, 'C5' => 0.09, 'C6' => 0.09, 'C7' => 0.10];
}

it('ranks by score when nothing is locked or escalated', function (): void {
    $placements = (new PriorityRanker)->rank(['a' => candidate(0.5), 'b' => candidate(0.9), 'c' => candidate(0.7)], 7);

    expect(finalOrder($placements))->toBe(['b', 'c', 'a'])
        ->and($placements['b']->systemRank)->toBe(1)
        ->and($placements['b']->finalRank)->toBe(1)
        ->and($placements['a']->group)->toBe(RankGroup::Regular);
});

it('breaks equal scores by the nearer deadline, then the earlier order date, then the key', function (): void {
    $placements = (new PriorityRanker)->rank([
        'late-deadline' => candidate(0.8, deadlineIn: 10, enteredAgo: 9),
        'early-deadline-new' => candidate(0.8, deadlineIn: 5, enteredAgo: 1),
        'early-deadline-old' => candidate(0.8, deadlineIn: 5, enteredAgo: 4),
        'same-as-old' => candidate(0.8, deadlineIn: 5, enteredAgo: 4),
    ], 7);

    expect(finalOrder($placements))->toBe(['early-deadline-old', 'same-as-old', 'early-deadline-new', 'late-deadline']);
});

it('does not depend on the order the candidates are given in', function (): void {
    $candidates = ['x' => candidate(0.6), 'y' => candidate(0.6), 'z' => candidate(0.6)];

    $forward = (new PriorityRanker)->rank($candidates, 7);
    $backward = (new PriorityRanker)->rank(array_reverse($candidates, true), 7);

    expect(finalOrder($forward))->toBe(finalOrder($backward));
});

it('escalates an order that waited 8 days but not one that waited exactly 7 (limit 7)', function (): void {
    $placements = (new PriorityRanker)->rank([
        'high' => candidate(0.95, waiting: 1),
        'seven' => candidate(0.40, waiting: 7),
        'eight' => candidate(0.30, waiting: 8),
    ], 7);

    expect($placements['eight']->group)->toBe(RankGroup::Escalated)
        ->and($placements['seven']->group)->toBe(RankGroup::Regular)
        ->and(finalOrder($placements))->toBe(['eight', 'high', 'seven']);
});

it('puts the group order locked, escalated, regular', function (): void {
    $placements = (new PriorityRanker)->rank([
        'regular' => candidate(0.99, waiting: 0),
        'escalated' => candidate(0.20, waiting: 9),
        'locked' => candidate(0.10, waiting: 0, locked: true),
    ], 7);

    expect(finalOrder($placements))->toBe(['locked', 'escalated', 'regular'])
        ->and($placements['locked']->group)->toBe(RankGroup::Locked)
        ->and($placements['escalated']->group)->toBe(RankGroup::Escalated);
});

it('keeps the system rank pure while the final rank moves', function (): void {
    $placements = (new PriorityRanker)->rank([
        'regular' => candidate(0.99),
        'escalated' => candidate(0.20, waiting: 9),
        'locked' => candidate(0.10, locked: true),
    ], 7);

    expect($placements['regular']->systemRank)->toBe(1)
        ->and($placements['escalated']->systemRank)->toBe(2)
        ->and($placements['locked']->systemRank)->toBe(3)
        ->and($placements['regular']->finalRank)->toBe(3)
        ->and($placements['escalated']->finalRank)->toBe(2)
        ->and($placements['locked']->finalRank)->toBe(1);
});

it('orders the locked group by score and the escalated group by longest wait, then score', function (): void {
    $placements = (new PriorityRanker)->rank([
        'lock-low' => candidate(0.30, locked: true),
        'lock-high' => candidate(0.60, locked: true),
        'wait-9-low' => candidate(0.20, waiting: 9),
        'wait-12' => candidate(0.10, waiting: 12),
        'wait-9-high' => candidate(0.50, waiting: 9),
    ], 7);

    expect(finalOrder($placements))->toBe(['lock-high', 'lock-low', 'wait-12', 'wait-9-high', 'wait-9-low']);
});

it('never escalates a locked order or an order that is already being worked on', function (): void {
    $placements = (new PriorityRanker)->rank([
        'locked-old' => candidate(0.50, waiting: 30, locked: true),
        'started' => candidate(0.40, waiting: 30, queued: false),
    ], 7);

    expect($placements['locked-old']->group)->toBe(RankGroup::Locked)
        ->and($placements['started']->group)->toBe(RankGroup::Regular);
});

it('uses the limit it is given', function (): void {
    $candidates = ['five' => candidate(0.2, waiting: 6), 'top' => candidate(0.9)];

    expect((new PriorityRanker)->rank($candidates, 7)['five']->group)->toBe(RankGroup::Regular)
        ->and((new PriorityRanker)->rank($candidates, 5)['five']->group)->toBe(RankGroup::Escalated);
});

it('handles a single order and an empty list', function (): void {
    $placements = (new PriorityRanker)->rank(['only' => candidate(1.0)], 7);

    expect($placements['only']->systemRank)->toBe(1)
        ->and($placements['only']->finalRank)->toBe(1)
        ->and((new PriorityRanker)->rank([], 7))->toBe([]);
});

it('shows the old order beaten on score when it has just arrived (PRD 11.7, day 0)', function (): void {
    $results = (new SawCalculator)->calculate(weights117(), [
        'L' => ['C1' => 1, 'C2' => 2, 'C3' => 4, 'C4' => 3, 'C5' => 3, 'C6' => 2, 'C7' => 1],
        'B' => ['C1' => 3, 'C2' => 5, 'C3' => 4, 'C4' => 3, 'C5' => 1, 'C6' => 4, 'C7' => 1],
    ]);

    expect($results['L']->score)->toBe(0.667)
        ->and($results['B']->score)->toBe(0.94);

    $placements = (new PriorityRanker)->rank([
        'L' => candidate($results['L']->score, waiting: 0),
        'B' => candidate($results['B']->score, waiting: 0),
    ], 7);

    expect(finalOrder($placements))->toBe(['B', 'L']);
});

it('escalates the old order above the higher scoring newcomer after 8 days (PRD 11.7)', function (): void {
    $results = (new SawCalculator)->calculate(weights117(), [
        'L' => ['C1' => 1, 'C2' => 2, 'C3' => 4, 'C4' => 3, 'C5' => 3, 'C6' => 2, 'C7' => 5],
        'B2' => ['C1' => 3, 'C2' => 5, 'C3' => 4, 'C4' => 3, 'C5' => 1, 'C6' => 4, 'C7' => 1],
    ]);

    expect($results['L']->score)->toBe(0.667)
        ->and($results['B2']->score)->toBe(0.86);

    $placements = (new PriorityRanker)->rank([
        'L' => candidate($results['L']->score, waiting: 8),
        'B2' => candidate($results['B2']->score, waiting: 0),
    ], 7);

    // System rank is pure score; only the final rank shows the escalation.
    expect($placements['B2']->systemRank)->toBe(1)
        ->and($placements['L']->systemRank)->toBe(2)
        ->and($placements['L']->finalRank)->toBe(1)
        ->and($placements['B2']->finalRank)->toBe(2)
        ->and($placements['L']->group)->toBe(RankGroup::Escalated)
        ->and($placements['B2']->group)->toBe(RankGroup::Regular);
});
