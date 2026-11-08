<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'contact', 'urgency_level', 'is_active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Guidance shown on the form, following the usual 5 to 1 scale.
     *
     * @var array<int, string>
     */
    public const URGENCY_LABELS = [
        5 => '5 - Sangat mendesak',
        4 => '4 - Mendesak',
        3 => '3 - Biasa',
        2 => '2 - Rendah',
        1 => '1 - Sangat rendah',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgency_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductionOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
