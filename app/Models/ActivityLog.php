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

    /**
     * Writes one row of the activity log. The user is null when the system itself acted.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(string $action, ?Model $subject, ?array $old, ?array $new, ?User $by = null): self
    {
        return static::query()->create([
            'user_id' => $by?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * The kinds of activity that can appear in the log, for a filter.
     *
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        $options = [];

        foreach (static::query()->distinct()->orderBy('action')->pluck('action') as $action) {
            $options[$action] = (new static(['action' => $action]))->actionLabel();
        }

        asort($options);

        return $options;
    }

    /**
     * What the row is about in words, such as "Order ORD-2026-0001", or a dash when it is not about one record.
     */
    public function subjectLabel(): string
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof ProductionOrder => 'Order '.$subject->number,
            $subject instanceof Criterion => 'Kriteria '.$subject->code,
            $subject instanceof Evaluation => 'Evaluasi '.$subject->name,
            $subject instanceof Assessment => 'Penilaian '.($subject->order?->number ?? '?').', '.($subject->criterion?->code ?? '?'),
            default => '-',
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'order.created' => 'Order dibuat',
            'order.updated' => 'Order diubah',
            'criterion.created' => 'Kriteria dibuat',
            'criterion.updated' => 'Kriteria diubah',
            'order.locked' => 'Order dikunci',
            'order.unlocked' => 'Kunci dilepas',
            'evaluation.created' => 'Perhitungan dijalankan',
            'evaluation.submitted' => 'Evaluasi diajukan',
            'evaluation.approved' => 'Evaluasi disetujui',
            'evaluation.rejected' => 'Evaluasi ditolak',
            'evaluation.applied' => 'Prioritas diterapkan',
            'evaluation.canceled' => 'Evaluasi dibatalkan',
            'evaluation.rank_adjusted' => 'Peringkat disesuaikan',
            'evaluation.schedule_skipped' => 'Perhitungan terjadwal dilewati',
            'evaluation.schedule_failed' => 'Perhitungan terjadwal gagal',
            'assessment.updated' => 'Nilai penilaian diubah',
            'criterion.scales_updated' => 'Skala nilai diubah',
            'setting.updated' => 'Pengaturan diubah',
            'auth.login' => 'Masuk',
            'auth.logout' => 'Keluar',
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
            'reason' => 'Alasan',
            'release_reason' => 'Alasan pelepasan',
            'code' => 'Kode',
            'name' => 'Nama',
            'unit' => 'Satuan',
            'direction' => 'Arah nilai',
            'value_source' => 'Sumber nilai',
            'importance' => 'Tingkat kepentingan',
            'weight' => 'Bobot',
            'sort' => 'Urutan',
            'is_active' => 'Aktif',
            'raw_value' => 'Nilai',
            'top_n_priority' => 'Jumlah Prioritas Utama',
            'approval_required' => 'Persetujuan Pimpinan wajib',
            'stale_value_days' => 'Batas nilai manual kedaluwarsa (hari)',
            'incomplete_orders' => 'Order belum lengkap',
            'lock_in_progress' => 'Kunci otomatis order Dikerjakan',
            'max_wait_days' => 'Batas tunggu maksimum (hari)',
            'held_after_evaluations' => 'Evaluasi berturut-turut untuk Tertahan',
            'held_min_days' => 'Menunggu minimal untuk Tertahan (hari)',
            'company_name' => 'Nama perusahaan',
            'company_address' => 'Alamat perusahaan',
            'company_phone' => 'Telepon perusahaan',
            'company_logo' => 'Logo perusahaan',
            'schedule_enabled' => 'Evaluasi terjadwal aktif',
            'schedule_time' => 'Jam evaluasi terjadwal',
            'near_deadline_days' => 'Batas mendekati tenggat (hari)',
            'incomplete_count' => 'Jumlah order belum lengkap',
        ][$field] ?? $field;
    }

    private static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'kosong';
        }

        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }

        if ($field === 'incomplete_orders') {
            return $value === 'exclude' ? 'dikecualikan' : 'ikut dengan nilai terburuk';
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
