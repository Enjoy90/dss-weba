<?php

namespace App\Models;

use App\Enums\CriterionDirection;
use App\Enums\ValueSource;
use App\Models\Concerns\LogsActivity;
use App\Services\CriterionWeights;
use App\Services\ScaleRules;
use Database\Factories\CriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'unit', 'direction', 'value_source', 'importance', 'weight', 'sort', 'is_active'])]
class Criterion extends Model
{
    /** @use HasFactory<CriterionFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, string>
     */
    public const IMPORTANCE_LABELS = [
        5 => '5 - Sangat penting',
        4 => '4 - Penting',
        3 => '3 - Cukup penting',
        2 => '2 - Kurang penting',
        1 => '1 - Tidak terlalu penting',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $criterion): void {
            if (! $criterion->sort) {
                $criterion->sort = ((int) static::query()->max('sort')) + 1;
            }
        });

        static::saved(function (self $criterion): void {
            if (CriterionWeights::isRefreshing()) {
                return;
            }

            if ($criterion->wasRecentlyCreated || $criterion->wasChanged(['importance', 'is_active'])) {
                CriterionWeights::refresh();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CriterionDirection::class,
            'value_source' => ValueSource::class,
            'importance' => 'integer',
            'weight' => 'decimal:4',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function activityPrefix(): string
    {
        return 'criterion';
    }

    /**
     * @return HasMany<CriterionScale, $this>
     */
    public function scales(): HasMany
    {
        return $this->hasMany(CriterionScale::class)->orderByDesc('score');
    }

    /**
     * @param  Builder<Criterion>  $query
     * @return Builder<Criterion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Criterion>  $query
     * @return Builder<Criterion>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Converts a raw value into the 1 to 5 scale using the saved ranges, or null when no range matches.
     */
    public function scoreFor(float $value): ?int
    {
        return ScaleRules::convert(ScaleRules::rowsFromScales($this->scales), $value);
    }

    /**
     * True when the five ranges exist and fit the direction of this criterion.
     */
    public function hasValidScale(): bool
    {
        return ScaleRules::validate(ScaleRules::rowsFromScales($this->scales), $this->direction) === [];
    }

    public function weightPercent(): float
    {
        return round((float) $this->weight * 100, 2);
    }
}
