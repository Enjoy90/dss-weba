<?php

namespace App\Console\Commands;

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Services\Validation\SpearmanCorrelation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Compares the final ranking of an evaluation with the order of the company (task P10-04, target T4: rho at least 0.70).
 * The company order comes from a CSV file (order number, place). Until the answers of questionnaire J3 are in,
 * --by-deadline uses a stand-in: nearest deadline first, the way orders are usually picked without the system.
 */
class SpearmanValidation extends Command
{
    protected $signature = 'validation:spearman
        {--evaluation= : Nomor id evaluasi, bawaannya evaluasi terbaru yang tidak dibatalkan}
        {--company= : Berkas CSV urutan perusahaan: kolom nomor order dan urutan}
        {--by-deadline : Pakai urutan pengganti (tenggat terdekat dulu) bila urutan perusahaan belum ada}
        {--queued-only : Bandingkan order Antri saja, tanpa order yang sedang Dikerjakan}
        {--score-rank : Pakai peringkat skor SAW saja (sebelum kelompok terkunci dan eskalasi), bukan peringkat akhir}
        {--template= : Tulis templat CSV untuk diisi perusahaan, lalu berhenti}';

    protected $description = 'Menghitung korelasi Spearman antara peringkat sistem dan urutan perusahaan';

    public function handle(SpearmanCorrelation $spearman): int
    {
        $evaluation = $this->option('evaluation')
            ? Evaluation::query()->find($this->option('evaluation'))
            : Evaluation::query()->where('status', '!=', EvaluationStatus::Canceled->value)->latest('evaluated_at')->latest('id')->first();

        if ($evaluation === null) {
            $this->error('Belum ada evaluasi. Jalankan perhitungan lebih dulu.');

            return self::FAILURE;
        }

        $items = $evaluation->items()->orderBy('final_rank')->get();

        if ($this->option('queued-only')) {
            $items = $items->filter(fn (EvaluationItem $item): bool => ($item->order_snapshot['status'] ?? null) === 'queued')->values();
        }

        if ($this->option('template')) {
            return $this->writeTemplate($items, (string) $this->option('template'));
        }

        if ($this->option('company')) {
            $company = $this->readCompany((string) $this->option('company'));
            $source = 'urutan perusahaan dari '.basename((string) $this->option('company'));
        } elseif ($this->option('by-deadline')) {
            $company = $this->byDeadline($items);
            $source = 'urutan pengganti: tenggat terdekat dulu (bukan data perusahaan)';
        } else {
            $this->error('Pilih sumber urutan: --company=berkas.csv atau --by-deadline.');

            return self::FAILURE;
        }

        $rankColumn = $this->option('score-rank') ? 'system_rank' : 'final_rank';
        $system = $items->mapWithKeys(fn (EvaluationItem $item): array => [$this->number($item) => $item->{$rankColumn}])->all();
        $company = array_intersect_key($company, $system);

        try {
            $result = $spearman->compare($system, $company);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Evaluasi: {$evaluation->name} ({$result['n']} order). Pembanding: {$source}.");
        $this->line('Peringkat sistem: '.($rankColumn === 'system_rank' ? 'skor SAW saja' : 'peringkat akhir (terkunci, eskalasi, lalu skor)').'.');

        $rows = collect($result['rows'])
            ->sortBy('system')
            ->map(fn (array $row, string $number): array => [$number, $this->format($row['system']), $this->format($row['company']), $this->format($row['d']), $this->format($row['d2'])])
            ->values()
            ->all();

        $this->table(['Order', 'Peringkat sistem', 'Urutan pembanding', 'd', 'd kuadrat'], $rows);

        $rho = $result['rho'];
        $this->line('Jumlah d kuadrat: '.$this->format($result['sum_d2']).'. Rho Spearman: '.number_format($rho, 4, ',', '.').' ('.SpearmanCorrelation::strength($rho).').');

        $biggest = collect($result['rows'])->sortByDesc(fn (array $row): float => abs($row['d']))->take(3);
        $this->line('Selisih terbesar: '.$biggest->map(fn (array $row, string $number): string => $number.' (d = '.$this->format($row['d']).')')->implode(', ').'.');

        $rho >= 0.70
            ? $this->info('Sasaran T4 tercapai (rho minimal 0,70).')
            : $this->warn('Sasaran T4 belum tercapai (rho di bawah 0,70). Periksa order dengan selisih terbesar.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, EvaluationItem>  $items
     * @return array<string, int>
     */
    private function byDeadline(Collection $items): array
    {
        $sorted = $items->sortBy([
            fn (EvaluationItem $a, EvaluationItem $b): int => ($a->order_snapshot['deadline_date'] ?? '') <=> ($b->order_snapshot['deadline_date'] ?? ''),
            fn (EvaluationItem $a, EvaluationItem $b): int => $this->number($a) <=> $this->number($b),
        ])->values();

        return $sorted->mapWithKeys(fn (EvaluationItem $item, int $i): array => [$this->number($item) => $i + 1])->all();
    }

    /**
     * @return array<string, float>
     */
    private function readCompany(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Berkas {$path} tidak ditemukan.");
        }

        $result = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
            $cells = str_getcsv($line, str_contains($line, ';') ? ';' : ',');

            if ($i === 0 && ! is_numeric($cells[count($cells) - 1] ?? '')) {
                continue;
            }

            $place = trim((string) ($cells[count($cells) - 1] ?? ''));

            if ($place !== '' && is_numeric(str_replace(',', '.', $place))) {
                $result[trim($cells[0])] = (float) str_replace(',', '.', $place);
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, EvaluationItem>  $items
     */
    private function writeTemplate(Collection $items, string $path): int
    {
        $lines = ['Nomor order;Pelanggan;Tenggat;Urutan perusahaan'];

        foreach ($items->sortBy(fn (EvaluationItem $item): string => $this->number($item)) as $item) {
            $lines[] = implode(';', [$this->number($item), $item->order_snapshot['customer'] ?? '', $item->order_snapshot['deadline_date'] ?? '', '']);
        }

        file_put_contents($path, implode("\n", $lines)."\n");
        $this->info("Templat dibuat: {$path}. Isi kolom Urutan perusahaan (1 = dikerjakan paling dulu), lalu jalankan dengan --company.");

        return self::SUCCESS;
    }

    private function number(EvaluationItem $item): string
    {
        return (string) ($item->order_snapshot['number'] ?? $item->production_order_id);
    }

    private function format(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
