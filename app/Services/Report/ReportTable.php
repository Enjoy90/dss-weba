<?php

namespace App\Services\Report;

/**
 * One table of a report. Rows hold raw values (numbers, dates, text) in the order of the columns.
 * The optional totals row is drawn in bold after the last row.
 */
final class ReportTable
{
    /**
     * @param  array<int, ReportColumn>  $columns
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $totals
     */
    public function __construct(
        public readonly string $title,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?string $note = null,
        public readonly ?array $totals = null,
        public readonly string $empty = 'Tidak ada data untuk laporan ini.',
    ) {}

    /**
     * The rows as the text a reader sees on the screen and in the PDF.
     *
     * @return array<int, array<int, string>>
     */
    public function displayRows(?int $limit = null): array
    {
        $rows = $limit === null ? $this->rows : array_slice($this->rows, 0, $limit);

        return array_map(fn (array $row): array => ReportFormatter::row($this->columns, $row), $rows);
    }

    /**
     * @return array<int, string>|null
     */
    public function displayTotals(): ?array
    {
        return $this->totals === null ? null : ReportFormatter::row($this->columns, $this->totals);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_map(fn (ReportColumn $column): string => $column->title, $this->columns);
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
