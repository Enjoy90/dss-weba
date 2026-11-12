<?php

namespace App\Console\Commands;

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Services\Validation\ManualCalculationWorkbook;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Writes the manual calculation workbook of task P10-03 for a saved evaluation. With --verify the workbook is
 * opened in LibreOffice so that a spreadsheet program, not this application, computes the formulas, and the
 * largest difference is read back.
 */
class ManualCalculationExcel extends Command
{
    protected $signature = 'validation:manual-excel
        {path : Lokasi berkas .xlsx yang dibuat}
        {--evaluation= : Nomor id evaluasi, bawaannya evaluasi terbaru yang tidak dibatalkan}
        {--verify : Hitung ulang rumus dengan LibreOffice dan periksa selisihnya}';

    protected $description = 'Membuat berkas Excel perhitungan SAW manual dan membandingkannya dengan skor sistem';

    public function handle(ManualCalculationWorkbook $workbook): int
    {
        $evaluation = $this->option('evaluation')
            ? Evaluation::query()->find($this->option('evaluation'))
            : Evaluation::query()->where('status', '!=', EvaluationStatus::Canceled->value)->latest('evaluated_at')->latest('id')->first();

        if ($evaluation === null) {
            $this->error('Belum ada evaluasi. Jalankan perhitungan lebih dulu.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        $workbook->write($evaluation, $path);
        $this->info("Berkas dibuat: {$path} ({$evaluation->name}, {$evaluation->order_count} order).");

        if (! $this->option('verify')) {
            return self::SUCCESS;
        }

        $result = $this->recalculate($path);

        if ($result === null) {
            $this->error('LibreOffice tidak ditemukan atau gagal menghitung. Buka berkas di Excel dan lihat sel Lolos di lembar Hasil.');

            return self::FAILURE;
        }

        [$rows, $largest] = $result;
        $this->line("Order dibandingkan: {$rows}. Selisih terbesar: ".sprintf('%.10f', $largest).'.');

        if ($rows !== $evaluation->items()->count() || $largest >= ManualCalculationWorkbook::TOLERANCE) {
            $this->error('Tidak lolos: selisih harus di bawah 0,0001 untuk semua order.');

            return self::FAILURE;
        }

        $this->info('Lolos: semua selisih di bawah 0,0001.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: float}|null number of orders and the largest difference, computed by LibreOffice
     */
    private function recalculate(string $path): ?array
    {
        $soffice = $this->soffice();

        if ($soffice === null) {
            return null;
        }

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'spk-manual-'.uniqid();
        @mkdir($dir);

        // The first sheet (Hasil) is exported as CSV after LibreOffice computed every formula.
        $process = new Process([$soffice, '--headless', '--calc', '--convert-to', 'csv', '--outdir', $dir, $path]);
        $process->setTimeout(120);
        $process->run();

        $csv = $dir.DIRECTORY_SEPARATOR.pathinfo($path, PATHINFO_FILENAME).'.csv';

        if (! is_file($csv)) {
            return null;
        }

        $rows = 0;
        $largest = 0.0;

        foreach (array_slice(array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES)), 1) as $line) {
            if (($line[0] ?? '') === '') {
                break;
            }

            $rows++;
            $largest = max($largest, abs((float) ($line[3] ?? 0)));
        }

        @unlink($csv);
        @rmdir($dir);

        return [$rows, $largest];
    }

    private function soffice(): ?string
    {
        foreach (array_filter([getenv('SOFFICE') ?: null, 'C:\\Program Files\\LibreOffice\\program\\soffice.exe', '/usr/bin/soffice', '/usr/local/bin/soffice']) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $finder = new Process(PHP_OS_FAMILY === 'Windows' ? ['where', 'soffice'] : ['which', 'soffice']);
        $finder->run();

        return $finder->isSuccessful() ? trim(strtok($finder->getOutput(), "\n")) : null;
    }
}
