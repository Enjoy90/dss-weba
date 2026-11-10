<?php

namespace App\Services\Evaluation;

use App\Enums\RankGroup;
use App\Models\EvaluationItem;
use Illuminate\Support\Collection;

/**
 * Which orders get the Prioritas Utama label (PRD F11 and 7.7). The N slots count queued orders only,
 * taken from the top of the final ranking. Orders being worked on keep the top of the ranking but use no slot.
 * Manually locked and escalated queued orders sit at the top, and when they alone fill every slot the
 * leader and the manager should look at N or at the locks.
 */
final class PriorityPlan
{
    /**
     * @param  array<int, int>  $topOrderIds  production order ids that get the label, best rank first
     * @param  int  $reserved  queued orders held by a manual lock or by escalation
     */
    public function __construct(
        public readonly array $topOrderIds,
        public readonly int $slots,
        public readonly int $reserved,
    ) {}

    /**
     * @param  Collection<int, EvaluationItem>  $items
     * @param  callable(EvaluationItem): bool  $isQueued  whether the order is waiting in the queue
     */
    public static function for(Collection $items, int $slots, callable $isQueued): self
    {
        $queued = $items->sortBy('final_rank')->filter(fn (EvaluationItem $item): bool => $isQueued($item))->values();

        $reserved = $queued->filter(fn (EvaluationItem $item): bool => $item->rank_group === RankGroup::Escalated
            || ($item->rank_group === RankGroup::Locked && ($item->order_snapshot['lock_type'] ?? null) === 'manual'))->count();

        return new self(
            $queued->take($slots)->pluck('production_order_id')->map(fn ($id): int => (int) $id)->all(),
            $slots,
            $reserved,
        );
    }

    /**
     * True when manual locks and escalations alone fill every slot.
     */
    public function isFull(): bool
    {
        return $this->reserved >= $this->slots;
    }

    public function has(int $productionOrderId): bool
    {
        return in_array($productionOrderId, $this->topOrderIds, true);
    }
}
