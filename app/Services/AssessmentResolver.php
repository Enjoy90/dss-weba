<?php

namespace App\Services;

use App\Enums\AssessmentSource;
use App\Enums\Completeness;
use App\Enums\ValueSource;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out the value of every criterion for an order.
 *
 * Automatic criteria are always derived from the order itself, so they are never stored and
 * are fresh every time. Only manual values and the urgency override live in the assessments table.
 */
class AssessmentResolver
{
    public const DEFAULT_STALE_DAYS = 3;

    public function __construct(
        private readonly Carbon $today,
        private readonly int $staleDays,
    ) {}

    public static function make(?Carbon $today = null): self
    {
        return new self(
            ($today ?? today())->copy()->startOfDay(),
            max(0, (int) Setting::get('stale_value_days', self::DEFAULT_STALE_DAYS)),
        );
    }

    /**
     * @param  Collection<int, Assessment>  $assessments  assessments of this order only
     */
    public function resolve(ProductionOrder $order, Criterion $criterion, Collection $assessments): ResolvedValue
    {
        $assessment = $assessments->firstWhere('criterion_id', $criterion->id);

        return match ($criterion->value_source) {
            ValueSource::Deadline => $this->automatic($criterion, (float) $this->today->diffInDays($order->deadline_date, false)),
            ValueSource::Waiting => $this->automatic($criterion, (float) max(0, $order->order_date->diffInDays($this->today))),
            ValueSource::CustomerUrgency => $assessment?->is_overridden && $assessment->raw_value !== null
                ? $this->override($criterion, $assessment)
                : $this->automatic($criterion, (float) $order->customer->urgency_level),
            ValueSource::OrderValue => $order->unit_price !== null
                ? $this->automatic($criterion, (float) $order->unit_price * $order->quantity)
                : $this->manual($criterion, $assessment),
            ValueSource::Manual => $this->manual($criterion, $assessment),
        };
    }

    /**
     * @param  Collection<int, Criterion>  $criteria  active criteria with their scales loaded
     * @return Collection<int, ResolvedValue> keyed by criterion id
     */
    public function resolveAll(ProductionOrder $order, Collection $criteria): Collection
    {
        $assessments = $order->assessments;

        return $criteria->mapWithKeys(fn (Criterion $criterion): array => [
            $criterion->id => $this->resolve($order, $criterion, $assessments),
        ]);
    }

    /**
     * @param  Collection<int, ResolvedValue>  $values
     */
    public function completeness(Collection $values): Completeness
    {
        if ($values->contains(fn (ResolvedValue $value): bool => $value->source === AssessmentSource::Empty)) {
            return Completeness::Incomplete;
        }

        if ($values->contains(fn (ResolvedValue $value): bool => $value->stale)) {
            return Completeness::Stale;
        }

        return Completeness::Complete;
    }

    /**
     * Ids of the open orders whose completeness equals the given state.
     *
     * @param  Collection<int, Criterion>  $criteria
     * @return array<int, int>
     */
    public function orderIdsWith(Completeness $state, Collection $criteria): array
    {
        return ProductionOrder::query()
            ->open()
            ->with(['customer', 'assessments'])
            ->get()
            ->filter(fn (ProductionOrder $order): bool => $this->completeness($this->resolveAll($order, $criteria)) === $state)
            ->pluck('id')
            ->all();
    }

    private function automatic(Criterion $criterion, float $raw): ResolvedValue
    {
        return new ResolvedValue($criterion, $raw, AssessmentSource::Automatic, $criterion->scoreFor($raw), false, null, null);
    }

    private function override(Criterion $criterion, Assessment $assessment): ResolvedValue
    {
        $raw = (float) $assessment->raw_value;

        return new ResolvedValue($criterion, $raw, AssessmentSource::Override, $criterion->scoreFor($raw), false, $assessment->value_updated_at, $assessment->override_reason);
    }

    private function manual(Criterion $criterion, ?Assessment $assessment): ResolvedValue
    {
        if ($assessment === null || $assessment->raw_value === null) {
            return new ResolvedValue($criterion, null, AssessmentSource::Empty, 1, false, $assessment?->value_updated_at, null);
        }

        $raw = (float) $assessment->raw_value;

        return new ResolvedValue($criterion, $raw, AssessmentSource::Manual, $criterion->scoreFor($raw), $this->isStale($assessment), $assessment->value_updated_at, null);
    }

    /**
     * Stale means last updated more than the allowed number of calendar days ago.
     */
    private function isStale(Assessment $assessment): bool
    {
        if ($assessment->value_updated_at === null) {
            return true;
        }

        return $assessment->value_updated_at->copy()->startOfDay()->diffInDays($this->today) > $this->staleDays;
    }
}
