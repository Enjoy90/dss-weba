<?php

namespace App\Services\Saw;

use App\Enums\RankGroup;

final class RankPlacement
{
    public function __construct(
        public readonly int $systemRank,
        public readonly int $finalRank,
        public readonly RankGroup $group,
    ) {}
}
