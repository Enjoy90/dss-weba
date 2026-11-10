<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation;
use App\Models\EvaluationItem;

/**
 * Compares the final ranking of two evaluations order by order (PRD F11 function 6).
 * "Up" means the order has a better place in the second evaluation than in the first.
 */
class EvaluationComparison
{
    public const UP = 'up';

    public const DOWN = 'down';

    public const SAME = 'same';

    public const NEW = 'new';

    public const GONE = 'gone';

    /**
     * One row per order that appears in either evaluation, best place in the second evaluation first,
     * then the orders that only appear in the first.
     *
     * @return array<int, array{order_id: int, number: string, customer: string, from_rank: ?int, to_rank: ?int, change: ?int, kind: string, from_score: ?float, to_score: ?float}>
     */
    public function compare(Evaluation $from, Evaluation $to): array
    {
        $before = $this->index($from);
        $after = $this->index($to);
        $rows = [];

        foreach ($before + $after as $orderId => $unused) {
            $a = $before[$orderId] ?? null;
            $b = $after[$orderId] ?? null;
            $snapshot = ($b ?? $a)->order_snapshot;

            $change = $a !== null && $b !== null ? $a->final_rank - $b->final_rank : null;

            $rows[] = [
                'order_id' => (int) $orderId,
                'number' => $snapshot['number'],
                'customer' => $snapshot['customer'],
                'from_rank' => $a?->final_rank,
                'to_rank' => $b?->final_rank,
                'change' => $change,
                'kind' => match (true) {
                    $a === null => self::NEW,
                    $b === null => self::GONE,
                    $change > 0 => self::UP,
                    $change < 0 => self::DOWN,
                    default => self::SAME,
                },
                'from_score' => $a === null ? null : (float) $a->score,
                'to_score' => $b === null ? null : (float) $b->score,
            ];
        }

        usort($rows, fn (array $x, array $y): int => ($x['to_rank'] ?? PHP_INT_MAX) <=> ($y['to_rank'] ?? PHP_INT_MAX)
            ?: ($x['from_rank'] ?? PHP_INT_MAX) <=> ($y['from_rank'] ?? PHP_INT_MAX));

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function counts(array $rows): array
    {
        $counts = [self::UP => 0, self::DOWN => 0, self::SAME => 0, self::NEW => 0, self::GONE => 0];

        foreach ($rows as $row) {
            $counts[$row['kind']]++;
        }

        return $counts;
    }

    /**
     * @return array<int, EvaluationItem> keyed by production order id
     */
    private function index(Evaluation $evaluation): array
    {
        return $evaluation->items()->get()->keyBy('production_order_id')->all();
    }
}
