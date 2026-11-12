<?php

namespace App\Console\Commands;

use App\Services\Validation\SusScore;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Scores the SUS answers of the user acceptance test (task P10-05). The CSV has one respondent per row: name, role,
 * then the answers to statements 1 to 10.
 */
class SusValidation extends Command
{
    protected $signature = 'validation:sus
        {path? : Berkas CSV jawaban: nama, peran, lalu jawaban butir 1 sampai 10}
        {--template= : Tulis templat CSV untuk diisi, lalu berhenti}';

    protected $description = 'Menghitung skor SUS dari jawaban kuesioner UAT';

    public function handle(SusScore $sus): int
    {
        if ($this->option('template')) {
            $header = array_merge(['Nama', 'Peran'], array_map(fn (int $n): string => 'Butir '.$n, array_keys(SusScore::STATEMENTS)));
            file_put_contents((string) $this->option('template'), implode(';', $header)."\n");
            $this->info('Templat dibuat. Satu baris per responden, jawaban 1 (sangat tidak setuju) sampai 5 (sangat setuju).');

            return self::SUCCESS;
        }

        $path = (string) $this->argument('path');

        if ($path === '' || ! is_file($path)) {
            $this->error('Sebutkan berkas CSV jawaban, atau buat templat dengan --template=berkas.csv.');

            return self::FAILURE;
        }

        $rows = [];
        $scores = [];
        $byRole = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
            $cells = array_map('trim', str_getcsv($line, str_contains($line, ';') ? ';' : ','));

            if ($i === 0 && ! is_numeric($cells[2] ?? '')) {
                continue;
            }

            try {
                $score = $sus->score(array_map(fn (string $cell): int => ctype_digit($cell) ? (int) $cell : -1, array_slice($cells, 2, 10)));
            } catch (InvalidArgumentException $exception) {
                $this->error('Baris '.($i + 1).': '.$exception->getMessage());

                return self::FAILURE;
            }

            $scores[] = $score;
            $byRole[$cells[1] ?? '-'][] = $score;
            $rows[] = [$cells[0] ?? '-', $cells[1] ?? '-', $this->format($score)];
        }

        if ($scores === []) {
            $this->error('Belum ada jawaban di berkas itu.');

            return self::FAILURE;
        }

        $this->table(['Responden', 'Peran', 'Skor SUS'], $rows);

        foreach ($byRole as $role => $values) {
            $this->line("Rata-rata {$role}: ".$this->format(array_sum($values) / count($values)).' ('.count($values).' orang).');
        }

        $mean = array_sum($scores) / count($scores);
        $this->line('Rata-rata semua responden: '.$this->format($mean).' dari '.count($scores).' orang, nilai '.SusScore::grade($mean).'.');

        $mean >= 68
            ? $this->info('Sasaran T6 tercapai (skor SUS minimal 68).')
            : $this->warn('Sasaran T6 belum tercapai (skor SUS di bawah 68).');

        return self::SUCCESS;
    }

    private function format(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }
}
