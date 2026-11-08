<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationTrigger;
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
