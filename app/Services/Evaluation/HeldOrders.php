<?php

namespace App\Services\Evaluation;

use App\Enums\EvaluationStatus;
use App\Enums\OrderStatus;
use App\Enums\RankGroup;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use Illuminate\Support\Collection;

/**
 * Finds the orders that are held back (PRD F18 function 6): queued orders that stayed outside the priority
 * list in each of the last evaluations and have waited long enough, so a warning shows before the order
 * reaches the limit for escalation. Canceled evaluations do not count, as in the rank trend.
 */
class HeldOrders
{
    /**
     * Ids of the held orders. Empty until enough evaluations exist.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        $needed = EvaluationSettings::heldAfterEvaluations();

        $evaluations = Evaluation::query()
            ->where('status', '!=', EvaluationStatus::Canceled->value)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->limit($needed)
            ->get(['id', 'top_n']);

        if ($evaluations->count() < $needed) {
            return [];
        }

        // Outside the priority list means below the N best queued orders, the same ones that get the
        // Prioritas Utama label. An order that was already being worked on does not use up a place.
        $outside = EvaluationItem::query()
            ->whereIn('evaluation_id', $evaluations->pluck('id'))
            ->orderBy('final_rank')
            ->get(['evaluation_id', 'production_order_id', 'final_rank', 'order_snapshot'])
            ->groupBy('evaluation_id')
            ->flatMap(function (Collection $items, int $evaluationId) use ($evaluations): array {
                $topN = $evaluations->firstWhere('id', $evaluationId)->top_n;
                $queuedRank = 0;
                $missed = [];

                foreach ($items as $item) {
                    if (($item->order_snapshot['status'] ?? null) !== OrderStatus::Queued->value) {
                        continue;
                    }

                    if (++$queuedRank > $topN) {
                        $missed[] = $item->production_order_id;
                    }
                }

                return $missed;
            })
            ->countBy()
            ->filter(fn (int $count): bool => $count === $needed)
            ->keys();

        if ($outside->isEmpty()) {
            return [];
        }

        return ProductionOrder::query()
            ->where('status', OrderStatus::Queued->value)
            ->whereIn('id', $outside)
            ->whereDate('order_date', '<=', today()->subDays(EvaluationSettings::heldMinDays())->toDateString())
            ->get()
            ->reject(fn (ProductionOrder $order): bool => $order->isLocked())
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Ids of the queued, unlocked orders that the newest evaluation put in the escalation group.
     *
     * @return array<int, int>
     */
    public function escalatedIds(): array
    {
        $latest = Evaluation::query()
            ->where('status', '!=', EvaluationStatus::Canceled->value)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->first(['id']);

        if ($latest === null) {
            return [];
        }

        $escalated = EvaluationItem::query()
            ->where('evaluation_id', $latest->id)
            ->where('rank_group', RankGroup::Escalated->value)
            ->pluck('production_order_id');

        return ProductionOrder::query()
            ->where('status', OrderStatus::Queued->value)
            ->whereIn('id', $escalated)
            ->get()
            ->reject(fn (ProductionOrder $order): bool => $order->isLocked())
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The order ids for the dashboard list: held orders and escalated orders, each with its reason.
     *
     * @return Collection<int, string> reason by order id: "held" or "escalated"
     */
    public function watchList(): Collection
    {
        $reasons = collect($this->escalatedIds())->mapWithKeys(fn (int $id): array => [$id => 'escalated']);

        foreach ($this->ids() as $id) {
            $reasons[$id] = 'held';
        }

        return $reasons;
    }
}
