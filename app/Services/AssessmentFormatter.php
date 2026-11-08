<?php

namespace App\Services;

use App\Models\Criterion;

class AssessmentFormatter
{
    /**
     * Writes a raw value with its unit, for example "12,5 jam" or "Rp 55.500.000".
     */
    public static function raw(Criterion $criterion, ?float $value): string
    {
        if ($value === null) {
            return 'Belum diisi';
        }

        $number = self::number($value);

        return match ($criterion->unit) {
            'rupiah' => 'Rp '.self::number($value, 0),
            'persen' => $number.'%',
            'tingkat' => $number,
            null, '' => $number,
            default => $number.' '.$criterion->unit,
        };
    }

    /**
     * A plain number with Indonesian separators and no useless trailing zeros.
     */
    public static function number(float $value, int $decimals = 2): string
    {
        $formatted = number_format($value, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}
