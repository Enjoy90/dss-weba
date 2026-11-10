<?php

namespace App\Services\Report;

use App\Services\AssessmentFormatter;
use DateTimeInterface;

/**
 * Turns raw report values into the text shown on the screen and in the PDF: dates as dd/mm/yyyy and
 * numbers with a comma for decimals and a point for thousands.
 */
final class ReportFormatter
{
    /**
     * @param  array<int, ReportColumn>  $columns
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function row(array $columns, array $row): array
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $cells[] = self::cell($column, $row[$index] ?? null);
        }

        return $cells;
    }

    public static function cell(ReportColumn $column, mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        // Text in a number column, such as "Belum diisi", and an empty cell are shown as they are.
        if ($value === '' || (is_string($value) && $column->isNumeric() && ! is_numeric($value))) {
            return (string) $value;
        }

        return match ($column->kind) {
            ColumnKind::Text => (string) $value,
            ColumnKind::Integer => number_format((float) $value, 0, ',', '.'),
            ColumnKind::Decimal => $column->unit !== null
                ? AssessmentFormatter::withUnit($column->unit, (float) $value)
                : number_format((float) $value, $column->decimals, ',', '.'),
            ColumnKind::Date => $value instanceof DateTimeInterface ? $value->format('d/m/Y') : date('d/m/Y', strtotime((string) $value)),
            ColumnKind::DateTime => $value instanceof DateTimeInterface ? $value->format('d/m/Y H:i') : date('d/m/Y H:i', strtotime((string) $value)),
        };
    }
}
