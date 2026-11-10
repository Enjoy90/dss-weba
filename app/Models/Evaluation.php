<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
use App\Services\Evaluation\EvaluationSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'name', 'trigger', 'status', 'evaluated_at', 'evaluated_by', 'submitted_by', 'submitted_at',
    'approved_by', 'approved_at', 'rejection_note', 'applied_by', 'applied_at', 'top_n',
    'criteria_snapshot', 'settings_snapshot', 'order_count', 'notes',
])]
class Evaluation extends Model
{
    /**
     * The only columns that may change after an evaluation is saved: the approval flow (PRD F10 rule 1).
     * The time of the evaluation, its snapshots and the order count are written once.
     *
     * @var array<int, string>
     */
    public const WORKFLOW_COLUMNS = [
        'status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'rejection_note', 'applied_by', 'applied_at', 'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'trigger' => 'manual',
        'status' => 'draft',
        'order_count' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $evaluation): void {
            $locked = array_diff(array_keys($evaluation->getDirty()), self::WORKFLOW_COLUMNS);

            if ($locked !== []) {
                throw new LogicException('Evaluasi yang sudah tersimpan tidak dapat diubah, hanya statusnya yang boleh berubah. Kolom: '.implode(', ', $locked).'.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Evaluasi yang sudah tersimpan tidak dapat dihapus. Batalkan statusnya bila tidak dipakai.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => EvaluationTrigger::class,
            'status' => EvaluationStatus::class,
            'evaluated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
            'top_n' => 'integer',
            'order_count' => 'integer',
            'criteria_snapshot' => 'array',
            'settings_snapshot' => 'array',
        ];
    }

    /**
     * The name for a choice list: the name with the time of the calculation (unless the name has it already) and the status.
     */
    public function optionLabel(): string
    {
        $stamp = $this->evaluated_at->format('d/m/Y H:i');
        $name = str_contains((string) $this->name, $stamp) ? $this->name : $this->name.' ('.$stamp.')';

        return $name.', '.$this->status->label();
    }

    /**
     * Only the newest evaluation that is not canceled may be applied (PRD F11 rule 2).
     */
    public function isLatest(): bool
    {
        return static::query()
            ->where('status', '!=', EvaluationStatus::Canceled->value)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->value('id') === $this->id;
    }

    /**
     * Whether an earlier evaluation exists to compare with, so a missing previous rank means a new order
     * and not simply "nothing to compare".
     */
    public function hasEarlier(): bool
    {
        return static::query()->where('id', '<', $this->id)->where('status', '!=', EvaluationStatus::Canceled->value)->exists();
    }

    public function allowsSubmit(): bool
    {
        return $this->status === EvaluationStatus::Draft && EvaluationSettings::approvalRequired();
    }

    public function allowsDecision(): bool
    {
        return $this->status === EvaluationStatus::Submitted;
    }

    /**
     * Approved, or a draft when approval is not required, and the newest evaluation.
     */
    public function allowsApply(): bool
    {
        $ready = $this->status === EvaluationStatus::Approved
            || ($this->status === EvaluationStatus::Draft && ! EvaluationSettings::approvalRequired());

        return $ready && $this->isLatest();
    }

    public function allowsAdjust(): bool
    {
        return in_array($this->status, [EvaluationStatus::Draft, EvaluationStatus::Submitted], true);
    }

    public function allowsCancel(): bool
    {
        return in_array($this->status, [EvaluationStatus::Draft, EvaluationStatus::Submitted, EvaluationStatus::Approved], true);
    }

    /**
     * @return HasMany<EvaluationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EvaluationItem::class)->orderBy('final_rank');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
