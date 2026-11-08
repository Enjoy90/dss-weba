<?php

namespace App\Services;

use App\Enums\AssessmentSource;
use App\Models\Criterion;
use Illuminate\Support\Carbon;

/**
 * The value of one criterion for one order at one moment: where it came from,
 * the raw number and the 1 to 5 score it converts to.
 */
final class ResolvedValue
{
    public function __construct(
        public readonly Criterion $criterion,
        public readonly ?float $raw,
        public readonly AssessmentSource $source,
        public readonly ?int $score,
        public readonly bool $stale,
        public readonly ?Carbon $updatedAt,
        public readonly ?string $overrideReason,
    ) {}

    /**
     * A manual value that nobody has filled in yet. It counts as the worst score (1) in a calculation.
     */
    public function isMissing(): bool
    {
        return $this->raw === null;
    }
}
