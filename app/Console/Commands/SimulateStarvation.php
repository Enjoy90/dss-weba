<?php

namespace App\Console\Commands;

use App\Services\Validation\StarvationSimulation;
use Illuminate\Console\Command;

/**
 * Runs the starvation simulation of PRD 13.8 (task P10-06) without and with protection and prints the comparison.
 * It only reads the criteria, weights and scales, nothing is written.
 */
class SimulateStarvation extends Command
{
    protected $signature = 'validation:simulate-starvation
        {--days=30 : Lama simulasi (hari)}
        {--daily : Tampilkan juga peringkat order lama per hari}';

    protected $description = 'Simulasi order tertahan 30 hari: tanpa perlindungan dan dengan batas tunggu 5, 7, dan 10 hari';

    public function handle(): int
    {
        $simulation = new StarvationSimulation((int) $this->option('days'));

        $runs = [
            'Tanpa perlindungan' => $simulation->run(null),
            'Batas 5 hari' => $simulation->run(5, 5),
            'Batas 7 hari' => $simulation->run(7, 7),
            'Batas 10 hari' => $simulation->run(10, 10),
        ];

        $labels = [
            'old_first_in_list' => 'Hari order lama masuk daftar prioritas',
            'old_done' => 'Hari order lama dikerjakan',
            'old_best_rank' => 'Peringkat terbaik order lama',
            'old_wait' => 'Lama menunggu order lama (hari)',
            'overdue_outside_escalation' => 'Order melewati batas tanpa dieskalasi',
            'longest_overdue_wait' => 'Tunggu terlama tanpa dieskalasi (hari)',
            'escalated_orders' => 'Order yang dieskalasi',
            'urgent_bumped' => 'Pesanan mendesak tergeser eskalasi (order x hari)',
            'average_wait' => 'Rata-rata lama menunggu (hari)',
            'max_wait' => 'Lama menunggu maksimum (hari)',
            'late_finished' => 'Order selesai melewati tenggat',
            'still_open' => 'Order belum dikerjakan di akhir',
        ];

        $rows = [];

        foreach ($labels as $key => $label) {
            $row = [$label];

            foreach ($runs as $run) {
                $value = $run[$key];
                $row[] = $value === null ? 'tidak pernah' : (is_float($value) ? number_format($value, 2, ',', '.') : (string) $value);
            }

            $rows[] = $row;
        }

        $this->table(array_merge(['Ukuran'], array_keys($runs)), $rows);
        $this->line('Batas pembanding untuk "tanpa perlindungan" adalah 7 hari.');

        if ($this->option('daily')) {
            $daily = [];

            foreach (array_keys($runs['Tanpa perlindungan']['daily']) as $i) {
                $line = [$i];

                foreach ($runs as $run) {
                    $entry = $run['daily'][$i];
                    $line[] = $entry['old_rank'] === null ? 'selesai' : $entry['old_rank'].($entry['old_group'] === 'escalated' ? ' (eskalasi)' : '');
                }

                $daily[] = $line;
            }

            $this->table(array_merge(['Hari'], array_keys($runs)), $daily);
        }

        $met = $runs['Batas 7 hari']['overdue_outside_escalation'] === 0;
        $met ? $this->info('Sasaran T7 tercapai: dengan batas 7 hari tidak ada order yang melewati batas tanpa dieskalasi.')
            : $this->error('Sasaran T7 tidak tercapai.');

        return $met ? self::SUCCESS : self::FAILURE;
    }
}
