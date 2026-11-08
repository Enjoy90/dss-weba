<?php

namespace App\Models;

use App\Enums\RankGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'evaluation_id', 'production_order_id', 'order_snapshot', 'raw_values', 'scale_values', 'normalized_values',
    'weighted_values', 'score', 'system_rank', 'final_rank', 'previous_rank', 'waiting_days', 'rank_group',
    'adjustment_reason', 'warnings',
])]
class EvaluationItem extends Model
{
    /**
     * Only a manual adjustment of the final rank may change a saved row. The calculation itself never does.
     *
     * @var array<int, string>
     */
    public const ADJUSTABLE_COLUMNS = ['final_rank', 'adjustment_reason', 'updated_at'];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'waiting_days' => 0,
        'rank_group' => 'regular',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            $locked = array_diff(array_keys($item->getDirty()), self::ADJUSTABLE_COLUMNS);

            if ($locked !== []) {
                throw new LogicException('Hasil perhitungan yang sudah tersimpan tidak dapat diubah. Kolom: '.implode(', ', $locked).'.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Hasil perhitungan yang sudah tersimpan tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_snapshot' => 'array',
            'raw_values' => 'array',
            'scale_values' => 'array',
            'normalized_values' => 'array',
            'weighted_values' => 'array',
            'warnings' => 'array',
            'score' => 'decimal:6',
            'system_rank' => 'integer',
            'final_rank' => 'integer',
            'previous_rank' => 'integer',
            'waiting_days' => 'integer',
            'rank_group' => RankGroup::class,
        ];
    }

    /**
     * @return BelongsTo<Evaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id')->withTrashed();
    }

    /**
     * How many places the order moved since the previous evaluation. Positive means it moved up.
     * Null when the order was not in the previous evaluation.
     */
    public function rankChange(): ?int
    {
        return $this->previous_rank === null ? null : $this->previous_rank - $this->final_rank;
    }
}
