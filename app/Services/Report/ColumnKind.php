<?php

namespace App\Services\Report;

/**
 * How a report column is written. The screen and the PDF show text in the Indonesian format, the Excel file keeps
 * numbers and dates as real numbers and dates so a reader can calculate with them.
 */
enum ColumnKind
{
    case Text;
    case Integer;
    case Decimal;
    case Date;
    case DateTime;

    public function isNumeric(): bool
    {
        return $this === self::Integer || $this === self::Decimal;
    }
}
