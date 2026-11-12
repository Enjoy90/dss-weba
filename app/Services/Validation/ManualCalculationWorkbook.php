<?php

namespace App\Services\Validation;

use App\Models\Evaluation;
use App\Models\EvaluationItem;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The manual calculation workbook of task P10-03 (PRD 13 point 4). Only the raw values and the scale tables of a saved
 * evaluation go in. Every scale, normalised value and score is an Excel formula, so the workbook recomputes the result
 * on its own and compares it with the score the system saved. The target is a difference below 0.0001 for every order.
 */
class ManualCalculationWorkbook
{
    /** Stand-in for an open bound of a scale range. */
    private const OPEN = 1.0E15;

    public const TOLERANCE = 0.0001;

    public function write(Evaluation $evaluation, string $path): void
    {
        $criteria = collect($evaluation->criteria_snapshot)->values();
        // The items relation sorts by final rank; this workbook is about the score, so it follows the system rank.
        $items = $evaluation->items()->reorder('system_rank')->get();
        $count = $items->count();
        $first = 2;
        $last = $first + $count - 1;
        $codes = $criteria->pluck('code')->all();
        $columns = array_map(fn (int $i): string => self::column($i + 2), array_keys($codes));
        $bold = (new Style)->setFontBold();

        $writer = new Writer;
        $writer->getOptions()->setColumnWidth(18, 1);
        $writer->getOptions()->setColumnWidthForRange(14, 2, 30);
        $writer->openToFile($path);

        // Sheet 1: result per order and the verdict.
        $writer->getCurrentSheet()->setName('Hasil');
        $writer->addRow(Row::fromValues(['Order', 'Skor Excel', 'Skor sistem', 'Selisih', 'Peringkat sistem'], $bold));

        foreach ($items as $n => $item) {
            $row = $first + $n;
            $writer->addRow(new Row([
                Cell::fromValue($this->number($item)),
                Cell::fromValue("=Skor!{$this->scoreColumn($codes)}{$row}"),
                Cell::fromValue((float) $item->score),
                Cell::fromValue("=ABS(B{$row}-C{$row})"),
                Cell::fromValue($item->system_rank),
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(new Row([Cell::fromValue('Selisih terbesar', $bold), Cell::fromValue('', $bold), Cell::fromValue('', $bold), Cell::fromValue("=MAX(D{$first}:D{$last})", $bold)]));
        $writer->addRow(new Row([Cell::fromValue('Batas selisih'), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(self::TOLERANCE)]));
        $writer->addRow(new Row([Cell::fromValue('Lolos', $bold), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue('=IF(D'.($last + 2).'<D'.($last + 3).',"Ya","Tidak")', $bold)]));

        // Sheet 2: weights.
        $writer->addNewSheetAndMakeItCurrent()->setName('Bobot');
        $writer->addRow(Row::fromValues(array_merge(['Kriteria'], $codes), $bold));
        $writer->addRow(Row::fromValues(array_merge(['Bobot'], $criteria->map(fn (array $c): float => (float) $c['weight'])->all())));
        $writer->addRow(Row::fromValues(array_merge(['Nama'], $criteria->pluck('name')->all())));
        $writer->addRow(Row::fromValues(['Jumlah bobot', '=SUM(B2:'.end($columns).'2)']));

        // Sheet 3: scale tables. Each criterion takes three columns (lower, upper, scale) and five rows.
        $writer->addNewSheetAndMakeItCurrent()->setName('Tabel skala');
        $header = [];
        $sub = [];

        foreach ($codes as $code) {
            array_push($header, $code, '', '');
            array_push($sub, 'Batas bawah', 'Batas atas', 'Skala');
        }

        $writer->addRow(Row::fromValues($header, $bold));
        $writer->addRow(Row::fromValues($sub, $bold));

        for ($r = 0; $r < 5; $r++) {
            $line = [];

            foreach ($criteria as $criterion) {
                $scale = collect($criterion['scales'])->sortByDesc('score')->values()[$r] ?? null;
                array_push(
                    $line,
                    $scale === null || $scale['lower_bound'] === null ? -self::OPEN : (float) $scale['lower_bound'],
                    $scale === null || $scale['upper_bound'] === null ? self::OPEN : (float) $scale['upper_bound'],
                    $scale['score'] ?? 0,
                );
            }

            $writer->addRow(Row::fromValues($line));
        }

        // Sheet 4: raw values (the test data). An empty cell is a missing value.
        $writer->addNewSheetAndMakeItCurrent()->setName('Nilai asli');
        $writer->addRow(Row::fromValues(array_merge(['Order'], $codes), $bold));

        foreach ($items as $item) {
            $values = [$this->number($item)];

            foreach ($codes as $code) {
                $raw = $item->raw_values[$code] ?? null;
                $values[] = $raw === null ? '' : (float) $raw;
            }

            $writer->addRow(Row::fromValues($values));
        }

        // Sheet 5: scale per order, from the raw value and the table. A missing value counts as scale 1, as in the system.
        $writer->addNewSheetAndMakeItCurrent()->setName('Skala');
        $writer->addRow(Row::fromValues(array_merge(['Order'], $codes), $bold));

        foreach ($items as $n => $item) {
            $row = $first + $n;
            $cells = [Cell::fromValue("='Nilai asli'!A{$row}")];

            foreach ($columns as $i => $col) {
                $lower = self::column($i * 3 + 1);
                $upper = self::column($i * 3 + 2);
                $score = self::column($i * 3 + 3);
                $raw = "'Nilai asli'!{$col}{$row}";
                $cells[] = Cell::fromValue("=IF({$raw}=\"\",1,SUMPRODUCT(({$raw}>='Tabel skala'!{$lower}\$3:{$lower}\$7)*({$raw}<'Tabel skala'!{$upper}\$3:{$upper}\$7)*'Tabel skala'!{$score}\$3:{$score}\$7))");
            }

            $writer->addRow(new Row($cells));
        }

        // Sheet 6: normalisation r = x / max(x).
        $writer->addNewSheetAndMakeItCurrent()->setName('Normalisasi');
        $writer->addRow(Row::fromValues(array_merge(['Order'], $codes), $bold));

        foreach ($items as $n => $item) {
            $row = $first + $n;
            $cells = [Cell::fromValue("='Nilai asli'!A{$row}")];

            foreach ($columns as $col) {
                $cells[] = Cell::fromValue("=Skala!{$col}{$row}/MAX(Skala!{$col}\${$first}:{$col}\${$last})");
            }

            $writer->addRow(new Row($cells));
        }

        // Sheet 7: weighted values and the score V = sum of w * r.
        $writer->addNewSheetAndMakeItCurrent()->setName('Skor');
        $writer->addRow(Row::fromValues(array_merge(['Order'], $codes, ['Skor']), $bold));

        foreach ($items as $n => $item) {
            $row = $first + $n;
            $cells = [Cell::fromValue("='Nilai asli'!A{$row}")];

            foreach ($columns as $col) {
                $cells[] = Cell::fromValue("=Bobot!{$col}\$2*Normalisasi!{$col}{$row}");
            }

            $cells[] = Cell::fromValue('=SUM(B'.$row.':'.end($columns).$row.')');
            $writer->addRow(new Row($cells));
        }

        $writer->close();
    }

    private function scoreColumn(array $codes): string
    {
        return self::column(count($codes) + 2);
    }

    private function number(EvaluationItem $item): string
    {
        return (string) ($item->order_snapshot['number'] ?? $item->production_order_id);
    }

    /**
     * Spreadsheet column letters for a 1-based index.
     */
    public static function column(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
