<?php

namespace App\Services\Evaluation;

use RuntimeException;

/**
 * Some orders are incomplete or stale and the caller has not confirmed to go on anyway.
 */
class EvaluationNeedsConfirmationException extends RuntimeException
{
    public function __construct(public readonly EvaluationPreflight $preflight)
    {
        parent::__construct(sprintf(
            '%d order belum lengkap dan %d order perlu diperbarui. Konfirmasi dulu bila tetap ingin menghitung.',
            count($preflight->incomplete),
            count($preflight->stale),
        ));
    }
}
