<?php

namespace App\Services\Saw;

use App\Enums\RankGroup;

/**
 * Turns scores into the system rank and the final rank (PRD 7.3 and 7.7).
 *
 * The system rank only follows the score. The final rank puts three groups one after another:
 * locked orders, escalated orders (queued, not locked, waiting longer than the limit) and the rest.
 */
class PriorityRanker
{
    /**
     * @param  array<int|string, RankCandidate>  $candidates
     * @param  int  $maxWaitDays  an order waiting more than this many days is escalated; exactly this many is not
     * @return array<int|string, RankPlacement> keyed like $candidates
     */
    public function rank(array $candidates, int $maxWaitDays): array
    {
        $keys = array_keys($candidates);
        $groups = [];

        foreach ($candidates as $key => $candidate) {
            $groups[$key] = $this->groupOf($candidate, $maxWaitDays);
        }

        $bySystem = $keys;
        usort($bySystem, fn ($a, $b): int => $this->compareByScore($candidates[$a], $candidates[$b], $a, $b));

        $systemRank = array_flip($bySystem);

        $final = $keys;
        usort($final, function ($a, $b) use ($candidates, $groups): int {
            $order = $this->groupOrder($groups[$a]) <=> $this->groupOrder($groups[$b]);

            if ($order !== 0) {
                return $order;
            }

            if ($groups[$a] === RankGroup::Escalated) {
                // Longest wait first, then the higher score.
                $byWait = $candidates[$b]->waitingDays <=> $candidates[$a]->waitingDays;

                if ($byWait !== 0) {
                    return $byWait;
                }
            }

            return $this->compareByScore($candidates[$a], $candidates[$b], $a, $b);
        });

        $finalRank = array_flip($final);
        $placements = [];

        foreach ($keys as $key) {
            $placements[$key] = new RankPlacement($systemRank[$key] + 1, $finalRank[$key] + 1, $groups[$key]);
        }

        return $placements;
    }

    private function groupOf(RankCandidate $candidate, int $maxWaitDays): RankGroup
    {
        if ($candidate->locked) {
            return RankGroup::Locked;
        }

        if ($candidate->queued && $candidate->waitingDays > $maxWaitDays) {
            return RankGroup::Escalated;
        }

        return RankGroup::Regular;
    }

    private function groupOrder(RankGroup $group): int
    {
        return match ($group) {
            RankGroup::Locked => 0,
            RankGroup::Escalated => 1,
            RankGroup::Regular => 2,
        };
    }

    /**
     * Higher score first. On equal scores the nearer deadline wins, then the earlier order date,
     * then the lower key so the result never depends on input order.
     */
    private function compareByScore(RankCandidate $a, RankCandidate $b, int|string $keyA, int|string $keyB): int
    {
        return ($b->score <=> $a->score)
            ?: ($a->deadline <=> $b->deadline)
            ?: ($a->orderDate <=> $b->orderDate)
            ?: ($keyA <=> $keyB);
    }
}
