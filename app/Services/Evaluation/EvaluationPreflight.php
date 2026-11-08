<?php

namespace App\Services\Evaluation;

use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Services\ResolvedValue;
use Illuminate\Support\Collection;

/**
 * What the checks before a calculation found, together with the data they loaded so the
 * calculation does not have to read it a second time.
 */
final class EvaluationPreflight
{
    /**
     * @param  array<int, string>  $blockers  reasons the calculation cannot start at all
     * @param  array<int, array{order_id: int, number: string, criteria: array<int, string>}>  $incomplete  orders with a manual value still empty
     * @param  array<int, array{order_id: int, number: string, criteria: array<int, string>}>  $stale  complete orders with a manual value that is too old
     * @param  Collection<int, Criterion>  $criteria  active criteria with scales
     * @param  Collection<int, ProductionOrder>  $orders  open orders with customer, product and assessments
     * @param  array<int, Collection<int, ResolvedValue>>  $resolved  values per order id, keyed by criterion id
     */
    public function __construct(
        public readonly array $blockers,
        public readonly array $incomplete,
        public readonly array $stale,
        public readonly Collection $criteria,
        public readonly Collection $orders,
        public readonly array $resolved,
    ) {}

    public function canRun(): bool
    {
        return $this->blockers === [];
    }

    public function hasWarnings(): bool
    {
        return $this->incomplete !== [] || $this->stale !== [];
    }

    public function orderCount(): int
    {
        return $this->orders->count();
    }
}
