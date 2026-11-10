<?php

namespace App\Services\Report;

final class ReportColumn
{
    public function __construct(
        public readonly string $title,
        public readonly ColumnKind $kind = ColumnKind::Text,
        public readonly int $decimals = 4,
        public readonly ?string $unit = null,
    ) {}

    public static function text(string $title): self
    {
        return new self($title);
    }

    public static function integer(string $title): self
    {
        return new self($title, ColumnKind::Integer);
    }

    public static function decimal(string $title, int $decimals = 4, ?string $unit = null): self
    {
        return new self($title, ColumnKind::Decimal, $decimals, $unit);
    }

    public static function date(string $title): self
    {
        return new self($title, ColumnKind::Date);
    }

    public static function dateTime(string $title): self
    {
        return new self($title, ColumnKind::DateTime);
    }

    public function isNumeric(): bool
    {
        return $this->kind->isNumeric();
    }
}
