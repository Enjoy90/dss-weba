<?php

namespace App\Services\Validation;

use App\Enums\RankGroup;
use App\Models\Criterion;
use App\Services\Saw\PriorityRanker;
use App\Services\Saw\RankCandidate;
use App\Services\Saw\SawCalculator;
use App\Services\ScaleRules;
use Carbon\CarbonImmutable;

/**
 * The starvation simulation of PRD chapter 13 point 8 (task P10-06). It runs the same SAW calculator, scales and
 * ranker as the application, day by day, without touching the database.
 *
 * The plant is kept full on purpose: every day two urgent orders come in and two orders are finished, taken from the
 * top of the final ranking. One old order from a regular customer with a far deadline is there from day 0, as in the
 * example of PRD 11.7. Without escalation that order keeps losing to the new urgent ones.
 */
class StarvationSimulation
{
    public const OLD_ORDER = 'L';

    /**
     * @var array<string, float>
     */
    private array $weights = [];

    /**
     * @var array<string, array<int, array{score: int, lower: float|null, upper: float|null}>>
     */
    private array $scales = [];

    public function __construct(
        private readonly int $days = 30,
        private readonly int $arrivalsPerDay = 2,
        private readonly int $capacityPerDay = 2,
        private readonly int $topN = 5,
    ) {
        foreach (Criterion::query()->active()->ordered()->with('scales')->get() as $criterion) {
            $this->weights[$criterion->code] = (float) $criterion->weight;
            $this->scales[$criterion->code] = ScaleRules::rowsFromScales($criterion->scales);
        }
    }

    /**
     * @param  int|null  $maxWaitDays  null runs the control without escalation
     * @param  int  $referenceLimit  the wait limit used to count overdue orders, also for the control
     * @return array<string, mixed>
     */
    public function run(?int $maxWaitDays, int $referenceLimit = 7): array
    {
        $start = CarbonImmutable::parse('2026-01-05');
        $orders = $this->initialOrders();
        $sequence = 0;

        $oldFirstInList = null;
        $oldDone = null;
        $oldBestRank = PHP_INT_MAX;
        $overdueOutside = [];
        $escalated = [];
        $urgentBumped = 0;
        $finished = [];
        $daily = [];

        for ($day = 0; $day < $this->days; $day++) {
            for ($i = 0; $i < $this->arrivalsPerDay; $i++) {
                $sequence++;
                $orders['B'.$sequence] = $this->urgentOrder($day);
            }

            $queued = array_filter($orders, fn (array $order): bool => $order['done'] === null);
            $ranking = $this->rank($queued, $day, $start, $maxWaitDays);

            $byFinal = array_keys($ranking);
            usort($byFinal, fn ($a, $b): int => $ranking[$a]['final'] <=> $ranking[$b]['final']);
            $bySystem = array_keys($ranking);
            usort($bySystem, fn ($a, $b): int => $ranking[$a]['system'] <=> $ranking[$b]['system']);

            $priority = array_slice($byFinal, 0, $this->topN);
            $systemTop = array_slice($bySystem, 0, $this->topN);

            foreach ($ranking as $key => $place) {
                $wait = $day - $orders[$key]['entered'];

                if ($place['group'] === RankGroup::Escalated) {
                    $escalated[$key] = true;
                } elseif ($wait > $referenceLimit) {
                    $overdueOutside[$key] = max($overdueOutside[$key] ?? 0, $wait);
                }
            }

            // An urgent order that would be in the priority list by score alone, but is pushed out by escalated orders.
            foreach ($systemTop as $key) {
                if ($orders[$key]['urgent'] && ! in_array($key, $priority, true)) {
                    $urgentBumped++;
                }
            }

            if (isset($ranking[self::OLD_ORDER])) {
                $oldBestRank = min($oldBestRank, $ranking[self::OLD_ORDER]['final']);

                if ($oldFirstInList === null && in_array(self::OLD_ORDER, $priority, true)) {
                    $oldFirstInList = $day;
                }
            }

            $daily[] = [
                'day' => $day,
                'queued' => count($queued),
                'old_rank' => $ranking[self::OLD_ORDER]['final'] ?? null,
                'old_group' => isset($ranking[self::OLD_ORDER]) ? $ranking[self::OLD_ORDER]['group']->value : null,
                'escalated' => count(array_filter($ranking, fn (array $place): bool => $place['group'] === RankGroup::Escalated)),
            ];

            foreach (array_slice($byFinal, 0, $this->capacityPerDay) as $key) {
                $orders[$key]['done'] = $day;
                $finished[$key] = $day - $orders[$key]['entered'];

                if ($key === self::OLD_ORDER) {
                    $oldDone = $day;
                }
            }
        }

        $open = array_filter($orders, fn (array $order): bool => $order['done'] === null);
        $openWaits = array_map(fn (array $order): int => $this->days - 1 - $order['entered'], $open);
        $allWaits = array_merge(array_values($finished), array_values($openWaits));
        $late = count(array_filter($orders, fn (array $order): bool => $order['done'] !== null && $order['done'] > $order['due']));

        return [
            'max_wait_days' => $maxWaitDays,
            'old_first_in_list' => $oldFirstInList,
            'old_done' => $oldDone,
            'old_best_rank' => $oldBestRank === PHP_INT_MAX ? null : $oldBestRank,
            'old_wait' => $oldDone ?? ($this->days - 1),
            'overdue_outside_escalation' => count($overdueOutside),
            'longest_overdue_wait' => $overdueOutside === [] ? 0 : max($overdueOutside),
            'escalated_orders' => count($escalated),
            'urgent_bumped' => $urgentBumped,
            'average_wait' => $allWaits === [] ? 0.0 : round(array_sum($allWaits) / count($allWaits), 2),
            'max_wait' => $allWaits === [] ? 0 : max($allWaits),
            'finished' => count($finished),
            'still_open' => count($open),
            'late_finished' => $late,
            'daily' => $daily,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $queued
     * @return array<string, array{system: int, final: int, group: RankGroup}>
     */
    private function rank(array $queued, int $day, CarbonImmutable $start, ?int $maxWaitDays): array
    {
        $scores = [];

        foreach ($queued as $key => $order) {
            $raw = [
                'C1' => $order['due'] - $day,
                'C2' => $order['urgency'],
                'C3' => $order['material'],
                'C4' => $order['hours'],
                'C5' => $order['progress'],
                'C6' => $order['value'],
                'C7' => $day - $order['entered'],
            ];

            foreach ($this->scales as $code => $rows) {
                $scores[$key][$code] = ScaleRules::convert($rows, (float) $raw[$code]);
            }
        }

        $results = app(SawCalculator::class)->calculate($this->weights, $scores);
        $candidates = [];

        foreach ($queued as $key => $order) {
            $candidates[$key] = new RankCandidate(
                $results[$key]->score,
                $start->addDays($order['due']),
                $start->addDays($order['entered']),
                $day - $order['entered'],
                true,
                false,
            );
        }

        // The control has no escalation: a limit no order can pass.
        $placements = app(PriorityRanker::class)->rank($candidates, $maxWaitDays ?? PHP_INT_MAX);
        $ranking = [];

        foreach ($placements as $key => $placement) {
            $ranking[$key] = ['system' => $placement->systemRank, 'final' => $placement->finalRank, 'group' => $placement->group];
        }

        return $ranking;
    }

    /**
     * Day 0: the old order L of PRD 11.7 and eight regular orders with spread deadlines.
     *
     * @return array<string, array<string, mixed>>
     */
    private function initialOrders(): array
    {
        $orders = [self::OLD_ORDER => $this->order(0, 40, 2, 80, 30, 50, 15_000_000, false)];

        foreach ([6, 8, 10, 12, 14, 16, 18, 20] as $n => $due) {
            $orders['R'.($n + 1)] = $this->order(0, $due, 3, 80, 30, 50, 30_000_000, false);
        }

        return $orders;
    }

    /**
     * A new urgent order like B in PRD 11.7: due in three days, very urgent customer, high value, not started yet.
     *
     * @return array<string, mixed>
     */
    private function urgentOrder(int $day): array
    {
        return $this->order($day, $day + 3, 5, 80, 30, 0, 60_000_000, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function order(int $entered, int $due, int $urgency, int $material, int $hours, int $progress, int $value, bool $urgent): array
    {
        return compact('entered', 'due', 'urgency', 'material', 'hours', 'progress', 'value', 'urgent') + ['done' => null];
    }
}
