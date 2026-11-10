<?php

namespace App\Services\Evaluation;

use App\Enums\EvaluationStatus;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The approval flow of an evaluation (PRD F11): submit, approve or reject, apply, and cancel.
 * Draft goes to Submitted to Approved to Applied. When approval is not required the manager
 * can apply a draft directly. Every step is checked here on the server and leaves one log row.
 */
class EvaluationWorkflow
{
    public function submit(Evaluation $evaluation, User $by): Evaluation
    {
        return $this->step($evaluation, $by, 'submit', 'evaluation.submitted', function (Evaluation $evaluation) use ($by): void {
            if (! EvaluationSettings::approvalRequired()) {
                $this->fail('Persetujuan Pimpinan tidak diwajibkan. Evaluasi bisa langsung diterapkan.');
            }

            if ($evaluation->status !== EvaluationStatus::Draft) {
                $this->fail('Hanya evaluasi berstatus Draf yang bisa diajukan.');
            }

            $evaluation->update([
                'status' => EvaluationStatus::Submitted,
                'submitted_by' => $by->id,
                'submitted_at' => now(),
                'rejection_note' => null,
            ]);
        });
    }

    public function approve(Evaluation $evaluation, User $by): Evaluation
    {
        return $this->step($evaluation, $by, 'approve', 'evaluation.approved', function (Evaluation $evaluation) use ($by): void {
            if ($evaluation->status !== EvaluationStatus::Submitted) {
                $this->fail('Hanya evaluasi berstatus Diajukan yang bisa disetujui.');
            }

            $evaluation->update([
                'status' => EvaluationStatus::Approved,
                'approved_by' => $by->id,
                'approved_at' => now(),
            ]);
        });
    }

    /**
     * A rejected evaluation goes back to Draft with the note, so the manager can adjust it and submit it again.
     */
    public function reject(Evaluation $evaluation, User $by, ?string $note): Evaluation
    {
        $note = trim((string) $note);

        return $this->step($evaluation, $by, 'reject', 'evaluation.rejected', function (Evaluation $evaluation) use ($note): void {
            if ($evaluation->status !== EvaluationStatus::Submitted) {
                $this->fail('Hanya evaluasi berstatus Diajukan yang bisa ditolak.');
            }

            if ($note === '') {
                $this->fail('Catatan penolakan wajib diisi supaya Manajer tahu apa yang perlu diperbaiki.', 'note');
            }

            if (mb_strlen($note) > 500) {
                $this->fail('Catatan penolakan paling banyak 500 huruf.', 'note');
            }

            $evaluation->update([
                'status' => EvaluationStatus::Draft,
                'rejection_note' => $note,
                'submitted_by' => null,
                'submitted_at' => null,
            ]);
        }, ['rejection_note' => $note]);
    }

    /**
     * Marks the first N queued orders of the final ranking as Prioritas Utama and puts every other
     * order back to Normal, so a new evaluation replaces the label of the previous one.
     */
    public function apply(Evaluation $evaluation, User $by): Evaluation
    {
        return $this->step($evaluation, $by, 'apply', 'evaluation.applied', function (Evaluation $evaluation) use ($by): void {
            $approvalRequired = EvaluationSettings::approvalRequired();

            if ($evaluation->status === EvaluationStatus::Applied) {
                $this->fail('Evaluasi ini sudah diterapkan.');
            }

            if ($evaluation->status === EvaluationStatus::Canceled) {
                $this->fail('Evaluasi yang dibatalkan tidak bisa diterapkan.');
            }

            if ($approvalRequired && $evaluation->status !== EvaluationStatus::Approved) {
                $this->fail('Evaluasi harus disetujui Pimpinan sebelum diterapkan.');
            }

            if (! $approvalRequired && ! in_array($evaluation->status, [EvaluationStatus::Draft, EvaluationStatus::Approved], true)) {
                $this->fail('Evaluasi yang sudah diajukan harus diputuskan Pimpinan atau dibatalkan lebih dulu.');
            }

            if (! $evaluation->isLatest()) {
                $this->fail('Sudah ada evaluasi yang lebih baru. Hanya evaluasi terbaru yang bisa diterapkan.');
            }

            $plan = $this->planFor($evaluation);

            ProductionOrder::query()
                ->where('priority', OrderPriority::Top->value)
                ->whereNotIn('id', $plan->topOrderIds)
                ->get()
                ->each(fn (ProductionOrder $order) => $order->update(['priority' => OrderPriority::Normal]));

            ProductionOrder::query()
                ->whereIn('id', $plan->topOrderIds)
                ->where('priority', '!=', OrderPriority::Top->value)
                ->get()
                ->each(fn (ProductionOrder $order) => $order->update(['priority' => OrderPriority::Top]));

            $evaluation->update([
                'status' => EvaluationStatus::Applied,
                'applied_by' => $by->id,
                'applied_at' => now(),
            ]);
        }, fn (Evaluation $evaluation): array => [
            'top_n' => $evaluation->top_n,
            'top_orders' => ProductionOrder::query()->where('priority', OrderPriority::Top->value)->orderBy('number')->pluck('number')->all(),
        ]);
    }

    /**
     * The saved evaluation stays as it is, only its status becomes Dibatalkan.
     */
    public function cancel(Evaluation $evaluation, User $by): Evaluation
    {
        return $this->step($evaluation, $by, 'cancel', 'evaluation.canceled', function (Evaluation $evaluation): void {
            if (! $evaluation->allowsCancel()) {
                $this->fail('Evaluasi yang sudah diterapkan atau sudah dibatalkan tidak bisa dibatalkan lagi.');
            }

            $evaluation->update(['status' => EvaluationStatus::Canceled]);
        });
    }

    /**
     * The label plan of an evaluation as it stands now: queued status is read from the live order.
     */
    public function planFor(Evaluation $evaluation): PriorityPlan
    {
        $items = $evaluation->items()->get();
        $queued = ProductionOrder::query()
            ->whereIn('id', $items->pluck('production_order_id'))
            ->where('status', OrderStatus::Queued->value)
            ->pluck('id')
            ->all();

        return PriorityPlan::for($items, $evaluation->top_n ?? EvaluationSettings::topN(), fn ($item): bool => in_array($item->production_order_id, $queued, true));
    }

    /**
     * Runs one step in a transaction on a freshly read evaluation, then writes the log row.
     *
     * @param  callable(Evaluation): void  $change
     * @param  array<string, mixed>|callable(Evaluation): array<string, mixed>  $extra
     */
    private function step(Evaluation $evaluation, User $by, string $ability, string $action, callable $change, array|callable $extra = []): Evaluation
    {
        Gate::forUser($by)->authorize($ability, $evaluation);

        return DB::transaction(function () use ($evaluation, $by, $action, $change, $extra): Evaluation {
            $fresh = Evaluation::query()->lockForUpdate()->findOrFail($evaluation->id);
            $before = $fresh->status;

            $change($fresh);

            ActivityLog::record($action, $fresh, ['status' => $before->value], [
                'status' => $fresh->status->value,
                'name' => $fresh->name,
            ] + (is_callable($extra) ? $extra($fresh) : $extra), $by);

            return $fresh;
        });
    }

    private function fail(string $message, string $field = 'evaluation'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
