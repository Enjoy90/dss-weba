<?php

namespace App\Services\Saw;

use Carbon\CarbonInterface;

/**
 * What the ranker needs to know about one order. Whether the lock is still in force is decided
 * by the caller, so the ranker stays free of settings and of the clock.
 */
final class RankCandidate
{
    public function __construct(
        public readonly float $score,
        public readonly CarbonInterface $deadline,
        public readonly CarbonInterface $orderDate,
        public readonly int $waitingDays,
        public readonly bool $queued,
        public readonly bool $locked,
    ) {}
}
