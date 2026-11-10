<?php

namespace App\Services\Report;

use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a ReportDocument as an Excel file. The first sheet is the letterhead and the facts about the report,
 * each table follows on a sheet of its own. Numbers and dates are real numbers and dates, not text,
 * so a reader can calculate with them again (PRD F13 acceptance 2).
 */
class ReportXlsx
{
    private const DATE_FORMAT = 'dd/mm/yyyy';

    private const DATE_TIME_FORMAT = 'dd/mm/yyyy hh:mm';

    public function write(ReportDocument $document, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Ringkasan');
        $this->summary($writer, $document);

        $used = ['Ringkasan'];

        foreach ($document->tables as $table) {
            $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName($table->title, $used));
            $this->table($writer, $table);
        }

        $writer->close();
    }

    private function summary(Writer $writer, ReportDocument $document): void
    {
        $bold = (new Style)->setFontBold();

        $writer->addRow(new Row([Cell::fromValue($document->company->name, (new Style)->setFontBold()->setFontSize(14))]));

        foreach ($document->company->lines() as $line) {
            $writer->addRow(Row::fromValues([$line]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(new Row([Cell::fromValue($document->title, $bold)]));

        foreach ($document->meta as $label => $value) {
            $writer->addRow(Row::fromValues([$label, $value]));
        }

        $writer->addRow(Row::fromValues(['Tanggal cetak', $document->printedAt->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValues(['Dicetak oleh', $document->printedBy]));
    }

    private function table(Writer $writer, ReportTable $table): void
    {
        $bold = (new Style)->setFontBold();

        $writer->addRow(new Row(array_map(
            fn (ReportColumn $column): Cell => Cell::fromValue($column->title, $bold),
            $table->columns,
        )));

        foreach ($table->rows as $row) {
            $writer->addRow($this->row($table->columns, $row));
        }

        if ($table->totals !== null) {
            $writer->addRow($this->row($table->columns, $table->totals, $bold));
        }

        if (filled($table->note)) {
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues([$table->note]));
        }
    }

    /**
     * @param  array<int, ReportColumn>  $columns
     * @param  array<int, mixed>  $values
     */
    private function row(array $columns, array $values, ?Style $style = null): Row
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $cells[] = $this->cell($column, $values[$index] ?? null, $style);
        }

        return new Row($cells);
    }

    private function cell(ReportColumn $column, mixed $value, ?Style $base): Cell
    {
        $style = $base ?? new Style;

        if ($value === null || $value === '') {
            return Cell::fromValue('', $style);
        }

        return match ($column->kind) {
            ColumnKind::Text => Cell::fromValue((string) $value, $style),
            ColumnKind::Integer => is_numeric($value) ? Cell::fromValue((int) $value, $style) : Cell::fromValue((string) $value, $style),
            ColumnKind::Decimal => is_numeric($value)
                ? Cell::fromValue((float) $value, $column->unit === null ? $this->withFormat($style, '0.'.str_repeat('0', $column->decimals)) : $style)
                : Cell::fromValue((string) $value, $style),
            ColumnKind::Date => Cell::fromValue($this->date($value), $this->withFormat($style, self::DATE_FORMAT)),
            ColumnKind::DateTime => Cell::fromValue($this->date($value), $this->withFormat($style, self::DATE_TIME_FORMAT)),
        };
    }

    private function withFormat(Style $style, string $format): Style
    {
        return (clone $style)->setFormat($format);
    }

    private function date(mixed $value): DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : new DateTimeImmutable((string) $value);
    }

    /**
     * An Excel sheet name: at most 31 characters, none of []:*?/\ and not used before on this file.
     *
     * @param  array<int, string>  $used
     */
    private function sheetName(string $title, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title) ?? ''), 0, 31) ?: 'Tabel';
        $name = $base;
        $suffix = 2;

        while (in_array($name, $used, true)) {
            $name = mb_substr($base, 0, 28).' '.$suffix++;
        }

        $used[] = $name;

        return $name;
    }
}
