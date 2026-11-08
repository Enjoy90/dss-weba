<?php

namespace App\Models;

use Database\Factories\CriterionScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['criterion_id', 'score', 'label', 'lower_bound', 'upper_bound'])]
class CriterionScale extends Model
{
    /** @use HasFactory<CriterionScaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Criterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
