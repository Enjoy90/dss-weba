<?php

namespace App\Services\Evaluation;

use RuntimeException;

/**
 * The calculation cannot start: weights, scales or orders are not ready.
 */
class EvaluationBlockedException extends RuntimeException
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
