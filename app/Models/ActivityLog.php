<?php

namespace App\Models;

use App\Enums\CriterionDirection;
use App\Enums\LockType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\ValueSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'order.created' => 'Order dibuat',
            'order.updated' => 'Order diubah',
            'criterion.created' => 'Kriteria dibuat',
            'criterion.updated' => 'Kriteria diubah',
            default => $this->action,
        };
    }

    /**
     * One readable line per changed field, for example "Status: Antri menjadi Dikerjakan".
     *
     * @return array<int, string>
     */
    public function changeLines(bool $hidePrices = false): array
    {
        if (str_ends_with($this->action, '.created')) {
            return [];
        }

        $lines = [];

        foreach ($this->new_values ?? [] as $field => $new) {
            if ($hidePrices && $field === 'unit_price') {
                continue;
            }

            $old = ($this->old_values ?? [])[$field] ?? null;

            $lines[] = sprintf(
                '%s: %s menjadi %s',
                self::fieldLabel($field),
                self::displayValue($field, $old),
                self::displayValue($field, $new),
            );
        }

        return $lines;
    }

    private static function fieldLabel(string $field): string
    {
        return [
            'number' => 'Nomor',
            'customer_id' => 'Pelanggan',
            'product_id' => 'Produk',
            'quantity' => 'Jumlah',
            'order_date' => 'Tanggal masuk',
            'deadline_date' => 'Tenggat',
            'unit_price' => 'Harga satuan',
            'status' => 'Status',
            'priority' => 'Prioritas',
            'lock_type' => 'Jenis kunci',
            'lock_reason' => 'Alasan kunci',
            'locked_by' => 'Dikunci oleh',
            'locked_at' => 'Waktu kunci',
            'lock_until' => 'Kunci berakhir',
            'notes' => 'Catatan',
            'code' => 'Kode',
            'name' => 'Nama',
            'unit' => 'Satuan',
            'direction' => 'Arah nilai',
            'value_source' => 'Sumber nilai',
            'importance' => 'Tingkat kepentingan',
            'weight' => 'Bobot',
            'sort' => 'Urutan',
            'is_active' => 'Aktif',
        ][$field] ?? $field;
    }

    private static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'kosong';
        }

        return match ($field) {
            'status' => OrderStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'priority' => OrderPriority::tryFrom((string) $value)?->label() ?? (string) $value,
            'lock_type' => LockType::tryFrom((string) $value)?->label() ?? (string) $value,
            'customer_id' => Customer::query()->whereKey($value)->value('name') ?? (string) $value,
            'product_id' => Product::query()->whereKey($value)->value('name') ?? (string) $value,
            'locked_by' => User::query()->whereKey($value)->value('name') ?? (string) $value,
            'order_date', 'deadline_date', 'lock_until' => Carbon::parse((string) $value)->translatedFormat('d M Y'),
            'unit_price' => 'Rp '.number_format((float) $value, 0, ',', '.'),
            'weight' => number_format((float) $value, 4, ',', '.'),
            'is_active' => $value ? 'ya' : 'tidak',
            'direction' => CriterionDirection::tryFrom((string) $value)?->label() ?? (string) $value,
            'value_source' => ValueSource::tryFrom((string) $value)?->label() ?? (string) $value,
            default => (string) $value,
        };
    }
}
