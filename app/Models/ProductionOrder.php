<?php

namespace App\Models;

use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'number', 'customer_id', 'product_id', 'quantity', 'order_date', 'deadline_date', 'unit_price',
    'status', 'priority', 'lock_type', 'lock_reason', 'locked_by', 'locked_at', 'lock_until',
    'notes', 'created_by', 'updated_by',
])]
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const NEAR_DEADLINE_DAYS = 3;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'queued',
        'priority' => 'normal',
        'lock_type' => 'none',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (blank($order->number)) {
                $order->number = static::nextNumber();
            }

            if (blank($order->created_by) && auth()->check()) {
                $order->created_by = auth()->id();
            }
        });

        static::updating(function (self $order): void {
            if (auth()->check()) {
                $order->updated_by = auth()->id();
            }
        });
    }

    public static function nextNumber(): string
    {
        $prefix = 'ORD-'.today()->format('Y').'-';

        $last = static::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'deadline_date' => 'date',
            'lock_until' => 'date',
            'locked_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'status' => OrderStatus::class,
            'priority' => OrderPriority::class,
            'lock_type' => LockType::class,
        ];
    }

    protected function activityPrefix(): string
    {
        return 'order';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * @return HasManyThrough<AssessmentHistory, Assessment, $this>
     */
    public function assessmentHistories(): HasManyThrough
    {
        return $this->hasManyThrough(AssessmentHistory::class, Assessment::class, 'production_order_id', 'assessment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     * @return Builder<ProductionOrder>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', OrderStatus::openValues());
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     * @return Builder<ProductionOrder>
     */
    public function scopeLate(Builder $query): Builder
    {
        return $query->open()->whereDate('deadline_date', '<', today());
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     * @return Builder<ProductionOrder>
     */
    public function scopeNearDeadline(Builder $query): Builder
    {
        return $query->open()->whereBetween('deadline_date', [
            today()->toDateString(),
            today()->addDays(self::NEAR_DEADLINE_DAYS)->toDateString(),
        ]);
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     * @return Builder<ProductionOrder>
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('lock_type', '!=', LockType::None->value);
    }

    public function isLate(): bool
    {
        return $this->status->isOpen() && $this->deadline_date->lt(today());
    }

    public function isLocked(): bool
    {
        return $this->lock_type->isLocked();
    }

    /**
     * Positive when the deadline is ahead, negative when it has passed.
     */
    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->deadline_date, false);
    }

    /**
     * Whole calendar days since the order came in. Only meaningful for queued orders.
     */
    public function waitingDays(): int
    {
        return (int) $this->order_date->diffInDays(today());
    }

    public function totalValue(): ?float
    {
        if ($this->unit_price === null) {
            return null;
        }

        return (float) $this->unit_price * $this->quantity;
    }
}
