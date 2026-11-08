<?php

namespace App\Services\Saw;

/**
 * The outcome of the SAW calculation for one alternative.
 */
final class SawResult
{
    /**
     * @param  array<string, float>  $normalized  r = x / max(x) per criterion
     * @param  array<string, float>  $weighted  w * r per criterion
     * @param  array<int, string>  $missing  criteria that had no score and were counted as 1
     */
    public function __construct(
        public readonly array $normalized,
        public readonly array $weighted,
        public readonly float $score,
        public readonly array $missing,
    ) {}
}
