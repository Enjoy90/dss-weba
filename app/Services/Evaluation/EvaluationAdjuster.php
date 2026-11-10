<?php

namespace App\Services\Evaluation;

use App\Enums\RankGroup;
use App\Models\ActivityLog;
use App\Models\EvaluationItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves one order to another place in the final ranking by hand (PRD F11 rule 3). The reason is required,
 * the system rank stays as it was, and a locked order cannot end up below an ordinary one.
 */
class EvaluationAdjuster
{
    public function move(EvaluationItem $item, int $newRank, ?string $reason, User $by): void
    {
        $evaluation = $item->evaluation;

        Gate::forUser($by)->authorize('adjust', $evaluation);

        if (! $evaluation->allowsAdjust()) {
            $this->fail('Peringkat hanya bisa disesuaikan pada evaluasi berstatus Draf atau Diajukan.', 'rank');
        }

        $reason = trim((string) $reason);

        if ($reason === '') {
            $this->fail('Alasan penyesuaian wajib diisi.', 'reason');
        }

        if (mb_strlen($reason) > 255) {
            $this->fail('Alasan paling banyak 255 huruf.', 'reason');
        }

        DB::transaction(function () use ($item, $newRank, $reason, $by, $evaluation): void {
            $items = EvaluationItem::query()
                ->where('evaluation_id', $evaluation->id)
                ->orderBy('final_rank')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $moved = $items->firstWhere('id', $item->id);

            if ($newRank < 1 || $newRank > $items->count()) {
                $this->fail('Peringkat harus antara 1 dan '.$items->count().'.', 'rank');
            }

            if ($moved->final_rank === $newRank) {
                $this->fail('Order ini sudah berada di peringkat '.$newRank.'.', 'rank');
            }

            $order = $items->reject(fn (EvaluationItem $row): bool => $row->id === $moved->id)->values()->all();
            array_splice($order, $newRank - 1, 0, [$moved]);

            $this->guardLockedAbove($order, $moved);

            $oldRank = $moved->final_rank;

            foreach ($order as $index => $row) {
                $rank = $index + 1;

                if ($row->id === $moved->id) {
                    $row->update(['final_rank' => $rank, 'adjustment_reason' => $reason]);
                } elseif ($row->final_rank !== $rank) {
                    $row->update(['final_rank' => $rank]);
                }
            }

            ActivityLog::record(
                'evaluation.rank_adjusted',
                $evaluation,
                ['order' => $moved->order_snapshot['number'], 'final_rank' => $oldRank],
                ['order' => $moved->order_snapshot['number'], 'final_rank' => $newRank, 'reason' => $reason],
                $by,
            );
        });
    }

    /**
     * A locked order may not end up below an ordinary one, and an ordinary one may not be put above a locked one.
     *
     * @param  array<int, EvaluationItem>  $order  the new ranking, best first
     */
    private function guardLockedAbove(array $order, EvaluationItem $moved): void
    {
        $lastLocked = null;
        $firstRegular = null;

        foreach ($order as $index => $row) {
            if ($row->rank_group === RankGroup::Locked) {
                $lastLocked = $index;
            }

            if ($row->rank_group === RankGroup::Regular && $firstRegular === null) {
                $firstRegular = $index;
            }
        }

        if ($lastLocked === null || $firstRegular === null || $lastLocked < $firstRegular) {
            return;
        }

        $this->fail($moved->rank_group === RankGroup::Locked
            ? 'Order terkunci tidak boleh turun ke bawah order biasa. Lepas kuncinya lebih dulu bila memang perlu.'
            : 'Order biasa tidak boleh diletakkan di atas order terkunci.', 'rank');
    }

    private function fail(string $message, string $field): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
