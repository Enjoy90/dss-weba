<?php

namespace App\Models;

use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['production_order_id', 'criterion_id', 'raw_value', 'is_overridden', 'override_reason', 'updated_by', 'value_updated_at'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_value' => 'decimal:2',
            'is_overridden' => 'boolean',
            'value_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * @return BelongsTo<Criterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<AssessmentHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(AssessmentHistory::class);
    }
}
