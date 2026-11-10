<?php

namespace App\Services\Evaluation;

use App\Enums\AssessmentSource;
use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssessmentResolver;
use App\Services\CriterionWeights;
use App\Services\ResolvedValue;
use App\Services\Saw\PriorityRanker;
use App\Services\Saw\RankCandidate;
use App\Services\Saw\SawCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs one evaluation (PRD F10): check, calculate and save a full copy of what was used,
 * all in one transaction. The saved copy never reads the live criteria again.
 */
class EvaluationService
{
    private const INSERT_CHUNK = 200;

    public function __construct(
        private readonly SawCalculator $calculator,
        private readonly PriorityRanker $ranker,
    ) {}

    /**
     * Looks at the data without saving anything. Day-level values (days left, days waiting)
     * follow the given moment, which defaults to now.
     */
    public function preflight(?CarbonInterface $at = null): EvaluationPreflight
    {
        $day = Carbon::instance($at ?? now())->startOfDay();
        $resolver = AssessmentResolver::make($day);

        $criteria = Criterion::query()->active()->with('scales')->ordered()->get();
        $orders = ProductionOrder::query()
            ->open()
            ->with(['customer', 'product', 'assessments'])
            ->orderBy('id')
            ->get();

        $resolved = [];
        $incomplete = [];
        $stale = [];

        foreach ($orders as $order) {
            $values = $resolver->resolveAll($order, $criteria);
            $resolved[$order->id] = $values;

            $empty = $this->codesWhere($values, fn (ResolvedValue $value): bool => $value->source === AssessmentSource::Empty);
            $old = $this->codesWhere($values, fn (ResolvedValue $value): bool => $value->stale);

            if ($empty !== []) {
                $incomplete[] = ['order_id' => $order->id, 'number' => $order->number, 'criteria' => $empty];
            } elseif ($old !== []) {
                $stale[] = ['order_id' => $order->id, 'number' => $order->number, 'criteria' => $old];
            }
        }

        $blockers = [];
        $weights = CriterionWeights::summary();

        if (! $weights['ready']) {
            $blockers[] = $weights['message'];
        }

        if ($orders->isEmpty()) {
            $blockers[] = 'Belum ada order berstatus Antri atau Dikerjakan yang bisa dihitung. Tambahkan order lebih dulu di menu Order Produksi.';
        }

        return new EvaluationPreflight($blockers, $incomplete, $stale, $criteria, $orders, $resolved);
    }

    /**
     * @throws EvaluationBlockedException when weights, scales or orders are not ready
     * @throws EvaluationNeedsConfirmationException when orders are incomplete or stale and $acknowledgeWarnings is false
     */
    public function run(
        ?User $by,
        EvaluationTrigger $trigger = EvaluationTrigger::Manual,
        ?string $name = null,
        ?string $notes = null,
        bool $acknowledgeWarnings = false,
        ?CarbonInterface $at = null,
    ): Evaluation {
        $moment = Carbon::instance($at ?? now());
        $preflight = $this->preflight($moment);

        if (! $preflight->canRun()) {
            throw new EvaluationBlockedException($preflight->blockers);
        }

        if ($preflight->hasWarnings() && ! $acknowledgeWarnings) {
            throw new EvaluationNeedsConfirmationException($preflight);
        }

        return DB::transaction(fn (): Evaluation => $this->store($preflight, $by, $trigger, $name, $notes, $moment));
    }

    /**
     * The settings that shape the calculation, read once and copied into the evaluation.
     *
     * @return array{weight_mode: string, stale_value_days: int, lock_in_progress: bool, max_wait_days: int, top_n_priority: int}
     */
    public function currentSettings(): array
    {
        return [
            'weight_mode' => CriterionWeights::mode(),
            'stale_value_days' => max(0, (int) Setting::get('stale_value_days', AssessmentResolver::DEFAULT_STALE_DAYS)),
            'lock_in_progress' => EvaluationSettings::lockInProgress(),
            'max_wait_days' => EvaluationSettings::maxWaitDays(),
            'top_n_priority' => EvaluationSettings::topN(),
        ];
    }

    private function store(
        EvaluationPreflight $preflight,
        ?User $by,
        EvaluationTrigger $trigger,
        ?string $name,
        ?string $notes,
        Carbon $moment,
    ): Evaluation {
        $day = $moment->copy()->startOfDay();
        $settings = $this->currentSettings();

        $weights = $preflight->criteria->mapWithKeys(fn (Criterion $criterion): array => [$criterion->code => (float) $criterion->weight])->all();

        $scores = [];

        foreach ($preflight->orders as $order) {
            foreach ($preflight->criteria as $criterion) {
                $scores[$order->id][$criterion->code] = $preflight->resolved[$order->id][$criterion->id]->score;
            }
        }

        $results = $this->calculator->calculate($weights, $scores);

        $candidates = [];
        $waiting = [];

        foreach ($preflight->orders as $order) {
            $waiting[$order->id] = max(0, (int) $order->order_date->diffInDays($day));

            $candidates[$order->id] = new RankCandidate(
                $results[$order->id]->score,
                $order->deadline_date,
                $order->order_date,
                $waiting[$order->id],
                $order->status === OrderStatus::Queued,
                $order->lockIsActive($day, $settings['lock_in_progress']),
            );
        }

        $placements = $this->ranker->rank($candidates, $settings['max_wait_days']);
        $previous = $this->previousRanks();

        $evaluation = Evaluation::query()->create([
            'name' => filled($name) ? trim($name) : 'Evaluasi '.$moment->format('d/m/Y H:i'),
            'trigger' => $trigger,
            'status' => EvaluationStatus::Draft,
            'evaluated_at' => $moment,
            'evaluated_by' => $by?->id,
            'top_n' => $settings['top_n_priority'],
            'criteria_snapshot' => $this->criteriaSnapshot($preflight->criteria),
            'settings_snapshot' => $settings + ['evaluation_date' => $day->toDateString()],
            'order_count' => $preflight->orderCount(),
            'notes' => filled($notes) ? trim($notes) : null,
        ]);

        $now = now();
        $rows = [];

        foreach ($preflight->orders as $order) {
            $result = $results[$order->id];
            $placement = $placements[$order->id];
            $values = $preflight->resolved[$order->id];

            $rows[] = [
                'evaluation_id' => $evaluation->id,
                'production_order_id' => $order->id,
                'order_snapshot' => json_encode($this->orderSnapshot($order)),
                'raw_values' => json_encode($this->byCode($preflight->criteria, fn (Criterion $c): ?float => $values[$c->id]->raw)),
                'scale_values' => json_encode($this->byCode($preflight->criteria, fn (Criterion $c): int => $scores[$order->id][$c->code] ?? 1)),
                'normalized_values' => json_encode($result->normalized),
                'weighted_values' => json_encode($result->weighted),
                'score' => $result->score,
                'system_rank' => $placement->systemRank,
                'final_rank' => $placement->finalRank,
                'previous_rank' => $previous[$order->id] ?? null,
                'waiting_days' => $waiting[$order->id],
                'rank_group' => $placement->group->value,
                'adjustment_reason' => null,
                'warnings' => json_encode($this->warnings($values)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            EvaluationItem::query()->insert($chunk);
        }

        ActivityLog::query()->create([
            'user_id' => $by?->id,
            'action' => 'evaluation.created',
            'subject_type' => $evaluation->getMorphClass(),
            'subject_id' => $evaluation->id,
            'old_values' => null,
            'new_values' => [
                'name' => $evaluation->name,
                'trigger' => $trigger->value,
                'order_count' => $evaluation->order_count,
            ],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);

        return $evaluation;
    }

    /**
     * Final rank of every order in the latest evaluation that was not canceled.
     *
     * @return array<int, int> rank keyed by order id
     */
    private function previousRanks(): array
    {
        $latest = Evaluation::query()
            ->where('status', '!=', EvaluationStatus::Canceled->value)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->value('id');

        if ($latest === null) {
            return [];
        }

        return EvaluationItem::query()
            ->where('evaluation_id', $latest)
            ->pluck('final_rank', 'production_order_id')
            ->map(fn ($rank): int => (int) $rank)
            ->all();
    }

    /**
     * @param  Collection<int, ResolvedValue>  $values
     * @return array<int, string>
     */
    private function codesWhere(Collection $values, callable $test): array
    {
        return $values->filter($test)->map(fn (ResolvedValue $value): string => $value->criterion->code)->values()->all();
    }

    /**
     * @param  Collection<int, ResolvedValue>  $values
     * @return array<int, array{criterion: string, type: string}>
     */
    private function warnings(Collection $values): array
    {
        $warnings = [];

        foreach ($values as $value) {
            if ($value->source === AssessmentSource::Empty) {
                $warnings[] = ['criterion' => $value->criterion->code, 'type' => 'empty'];
            } elseif ($value->stale) {
                $warnings[] = ['criterion' => $value->criterion->code, 'type' => 'stale'];
            }
        }

        return $warnings;
    }

    /**
     * @param  Collection<int, Criterion>  $criteria
     * @return array<string, mixed>
     */
    private function byCode(Collection $criteria, callable $value): array
    {
        return $criteria->mapWithKeys(fn (Criterion $criterion): array => [$criterion->code => $value($criterion)])->all();
    }

    /**
     * @param  Collection<int, Criterion>  $criteria
     * @return array<int, array<string, mixed>>
     */
    private function criteriaSnapshot(Collection $criteria): array
    {
        return $criteria->map(fn (Criterion $criterion): array => [
            'code' => $criterion->code,
            'name' => $criterion->name,
            'unit' => $criterion->unit,
            'direction' => $criterion->direction->value,
            'value_source' => $criterion->value_source->value,
            'importance' => $criterion->importance,
            'weight' => (float) $criterion->weight,
            'scales' => $criterion->scales->map(fn ($scale): array => [
                'score' => $scale->score,
                'label' => $scale->label,
                'lower_bound' => $scale->lower_bound === null ? null : (float) $scale->lower_bound,
                'upper_bound' => $scale->upper_bound === null ? null : (float) $scale->upper_bound,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Identifying details only. Prices are left out so the copy never carries rupiah figures by accident.
     *
     * @return array<string, mixed>
     */
    private function orderSnapshot(ProductionOrder $order): array
    {
        return [
            'number' => $order->number,
            'customer' => $order->customer->name,
            'product' => $order->product->name,
            'quantity' => $order->quantity,
            'order_date' => $order->order_date->toDateString(),
            'deadline_date' => $order->deadline_date->toDateString(),
            'status' => $order->status->value,
            'lock_type' => $order->lock_type->value,
            'lock_until' => $order->lock_until?->toDateString(),
        ];
    }
}
