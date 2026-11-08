<?php

namespace App\Services;

use App\Enums\ValueSource;
use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place that writes assessment values. It checks the value against the unit of the
 * criterion, stamps who and when, and records a history row whenever the value really changes.
 */
class AssessmentWriter
{
    /**
     * Sets or clears a manual value. Automatic criteria cannot be written.
     */
    public function setManual(ProductionOrder $order, Criterion $criterion, ?float $value, User $by): Assessment
    {
        if (! $this->isManualFor($order, $criterion)) {
            throw ValidationException::withMessages(['value' => 'Nilai kriteria ini diisi otomatis dan tidak dapat diubah dengan tangan.']);
        }

        $this->validateValue($criterion, $value);

        return DB::transaction(function () use ($order, $criterion, $value, $by): Assessment {
            $assessment = $this->assessmentFor($order, $criterion);

            if (! $assessment->exists && $value === null) {
                return $assessment;
            }

            $old = $assessment->raw_value === null ? null : (float) $assessment->raw_value;

            $assessment->fill([
                'raw_value' => $value,
                'is_overridden' => false,
                'override_reason' => null,
                'updated_by' => $by->id,
                'value_updated_at' => now(),
            ])->save();

            $this->recordHistory($assessment, $old, $value, $by);

            return $assessment;
        });
    }

    /**
     * Replaces the urgency taken from the customer with a value for this order only. A reason is required.
     */
    public function override(ProductionOrder $order, Criterion $criterion, float $value, ?string $reason, User $by): Assessment
    {
        if ($criterion->value_source !== ValueSource::CustomerUrgency) {
            throw ValidationException::withMessages(['value' => 'Hanya urgensi pelanggan yang dapat ditimpa.']);
        }

        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan menimpa urgensi wajib diisi.']);
        }

        if (mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan paling banyak 255 karakter.']);
        }

        $this->validateValue($criterion, $value);

        return DB::transaction(function () use ($order, $criterion, $value, $reason, $by): Assessment {
            $assessment = $this->assessmentFor($order, $criterion);
            $old = $assessment->is_overridden && $assessment->raw_value !== null ? (float) $assessment->raw_value : null;

            $assessment->fill([
                'raw_value' => $value,
                'is_overridden' => true,
                'override_reason' => $reason,
                'updated_by' => $by->id,
                'value_updated_at' => now(),
            ])->save();

            $this->recordHistory($assessment, $old, $value, $by);

            return $assessment;
        });
    }

    /**
     * Goes back to the urgency of the customer.
     */
    public function clearOverride(ProductionOrder $order, Criterion $criterion, User $by): void
    {
        $assessment = Assessment::query()
            ->where('production_order_id', $order->id)
            ->where('criterion_id', $criterion->id)
            ->first();

        if ($assessment === null || ! $assessment->is_overridden) {
            return;
        }

        DB::transaction(function () use ($assessment, $by): void {
            $old = $assessment->raw_value === null ? null : (float) $assessment->raw_value;

            $assessment->fill([
                'raw_value' => null,
                'is_overridden' => false,
                'override_reason' => null,
                'updated_by' => $by->id,
                'value_updated_at' => now(),
            ])->save();

            $this->recordHistory($assessment, $old, null, $by);
        });
    }

    /**
     * Manual criteria are written by hand. The order value is manual only while the order has no unit price.
     */
    public function isManualFor(ProductionOrder $order, Criterion $criterion): bool
    {
        return match ($criterion->value_source) {
            ValueSource::Manual => true,
            ValueSource::OrderValue => $order->unit_price === null,
            default => false,
        };
    }

    /**
     * Percentages stay between 0 and 100, hours and rupiah cannot be negative, urgency is a whole number from 1 to 5.
     *
     * @throws ValidationException
     */
    public function validateValue(Criterion $criterion, ?float $value): void
    {
        if ($value === null) {
            return;
        }

        $message = match ($criterion->unit) {
            'persen' => $value < 0 || $value > 100 ? 'Persentase harus antara 0 dan 100.' : null,
            'jam' => $value < 0 ? 'Jumlah jam tidak boleh negatif.' : null,
            'rupiah' => $value < 0 ? 'Nilai rupiah tidak boleh negatif.' : null,
            'hari' => $value < 0 ? 'Jumlah hari tidak boleh negatif.' : null,
            'tingkat' => $value < 1 || $value > 5 || $value != floor($value) ? 'Tingkat harus bilangan bulat dari 1 sampai 5.' : null,
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['value' => $message]);
        }
    }

    private function assessmentFor(ProductionOrder $order, Criterion $criterion): Assessment
    {
        return Assessment::query()->firstOrNew([
            'production_order_id' => $order->id,
            'criterion_id' => $criterion->id,
        ]);
    }

    private function recordHistory(Assessment $assessment, ?float $old, ?float $new, User $by): void
    {
        if ($old !== null && $new !== null && round($old, 2) === round($new, 2)) {
            return;
        }

        if ($old === null && $new === null) {
            return;
        }

        $assessment->histories()->create([
            'old_value' => $old,
            'new_value' => $new,
            'changed_by' => $by->id,
            'changed_at' => now(),
        ]);
    }
}
