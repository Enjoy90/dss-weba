<?php

namespace App\Services\Report;

use App\Enums\CriterionDirection;
use App\Enums\OrderStatus;
use App\Enums\ValueSource;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the five reports of PRD F13 as ReportDocument objects. Nothing here draws anything: the screen preview,
 * the PDF and the Excel file all read the same document, so their numbers cannot drift apart.
 * No report shows rupiah, except the raw values of the order value criterion in the calculation report,
 * which a user who may not see rupiah (the supervisor) does not get.
 */
class ReportBuilder
{
    public function ranking(Evaluation $evaluation, User $printer): ReportDocument
    {
        $items = $this->items($evaluation);
        $adjusted = $items->filter(fn (EvaluationItem $item): bool => $item->adjustment_reason !== null)->count();

        $rows = $items->map(fn (EvaluationItem $item): array => [
            $item->final_rank,
            $item->order_snapshot['number'],
            $item->order_snapshot['customer'],
            $item->order_snapshot['product'],
            Carbon::parse($item->order_snapshot['deadline_date']),
            $item->rank_group->label(),
            (float) $item->score,
            $item->system_rank,
            $item->adjustment_reason ?? '',
        ])->all();

        $criteria = collect($this->visibleCriteria($evaluation, $printer))->map(fn (array $criterion): array => [
            $criterion['code'].' '.$criterion['name'],
            (float) $criterion['weight'],
            CriterionDirection::from($criterion['direction'])->label(),
        ])->all();

        return $this->document('peringkat-evaluasi-'.$evaluation->id, 'Laporan Peringkat Prioritas Produksi', $this->evaluationMeta($evaluation), [
            new ReportTable('Peringkat prioritas', [
                ReportColumn::integer('Peringkat'),
                ReportColumn::text('Order'),
                ReportColumn::text('Pelanggan'),
                ReportColumn::text('Produk'),
                ReportColumn::date('Tenggat'),
                ReportColumn::text('Kelompok'),
                ReportColumn::decimal('Skor'),
                ReportColumn::integer('Peringkat sistem'),
                ReportColumn::text('Alasan penyesuaian'),
            ], $rows, $adjusted === 0
                ? 'Tidak ada penyesuaian manual. Peringkat akhir mengikuti kelompok (terkunci, eskalasi, biasa) lalu skor, peringkat sistem hanya menurut skor.'
                : $adjusted.' order disesuaikan secara manual, alasannya tertulis di kolom terakhir.',
                empty: 'Evaluasi ini tidak memuat order.'),
            new ReportTable('Kriteria dan bobot', [
                ReportColumn::text('Kriteria'),
                ReportColumn::decimal('Bobot'),
                ReportColumn::text('Arah nilai asli'),
            ], $criteria),
        ], $printer);
    }

    public function calculation(Evaluation $evaluation, User $printer): ReportDocument
    {
        $items = $this->items($evaluation);
        $criteria = $this->visibleCriteria($evaluation, $printer);
        $label = fn (EvaluationItem $item): string => $item->order_snapshot['number'].' ('.$item->order_snapshot['customer'].')';

        $matrix = function (string $title, callable $column, callable $cell, ?string $note = null, bool $withScore = false) use ($items, $criteria, $label): ReportTable {
            $columns = [ReportColumn::integer('Peringkat'), ReportColumn::text('Order')];

            foreach ($criteria as $criterion) {
                $columns[] = $column($criterion);
            }

            if ($withScore) {
                $columns[] = ReportColumn::decimal('Skor V');
            }

            $rows = $items->map(function (EvaluationItem $item) use ($criteria, $cell, $label, $withScore): array {
                $row = [$item->final_rank, $label($item)];

                foreach ($criteria as $criterion) {
                    $row[] = $cell($item, $criterion);
                }

                if ($withScore) {
                    $row[] = (float) $item->score;
                }

                return $row;
            })->all();

            return new ReportTable($title, $columns, $rows, $note, empty: 'Evaluasi ini tidak memuat order.');
        };

        $scales = [];

        foreach ($criteria as $criterion) {
            foreach ($criterion['scales'] as $index => $scale) {
                $scales[] = [
                    $index === 0 ? $criterion['code'].' '.$criterion['name'] : '',
                    $index === 0 ? (float) $criterion['weight'] : '',
                    $index === 0 ? CriterionDirection::from($criterion['direction'])->label() : '',
                    (int) $scale['score'],
                    $scale['label'] ?? '-',
                ];
            }
        }

        $divisors = collect($criteria)->map(fn (array $criterion): array => [
            $criterion['code'].' '.$criterion['name'],
            (float) $criterion['weight'],
            (int) ($items->max(fn (EvaluationItem $item): int => (int) ($item->scale_values[$criterion['code']] ?? 1)) ?? 1),
        ])->all();

        return $this->document('rincian-evaluasi-'.$evaluation->id, 'Laporan Rincian Perhitungan SAW', $this->evaluationMeta($evaluation), [
            new ReportTable('Kriteria dan skala', [
                ReportColumn::text('Kriteria'),
                ReportColumn::decimal('Bobot'),
                ReportColumn::text('Arah nilai asli'),
                ReportColumn::integer('Skala'),
                ReportColumn::text('Rentang'),
            ], $scales, empty: 'Evaluasi ini tidak punya kriteria yang bisa Anda lihat.'),
            new ReportTable('Bobot dan pembagi', [
                ReportColumn::text('Kriteria'),
                ReportColumn::decimal('Bobot'),
                ReportColumn::integer('Nilai skala tertinggi'),
            ], $divisors, 'Nilai normalisasi = nilai skala dibagi nilai skala tertinggi kolomnya. Skor V = jumlah dari bobot kali nilai normalisasi.'),
            $matrix('Nilai asli', fn (array $c): ReportColumn => ReportColumn::decimal($c['code'], 4, $c['unit']), fn (EvaluationItem $item, array $c): mixed => ($item->raw_values[$c['code']] ?? null) === null ? 'Belum diisi' : (float) $item->raw_values[$c['code']]),
            $matrix('Nilai skala 1 sampai 5', fn (array $c): ReportColumn => ReportColumn::integer($c['code']), fn (EvaluationItem $item, array $c): int => (int) ($item->scale_values[$c['code']] ?? 1)),
            $matrix('Nilai normalisasi', fn (array $c): ReportColumn => ReportColumn::decimal($c['code']), fn (EvaluationItem $item, array $c): float => (float) ($item->normalized_values[$c['code']] ?? 0)),
            $matrix('Nilai terbobot dan skor', fn (array $c): ReportColumn => ReportColumn::decimal($c['code'].' (bobot '.number_format((float) $c['weight'], 4, ',', '.').')'), fn (EvaluationItem $item, array $c): float => (float) ($item->weighted_values[$c['code']] ?? 0), withScore: true),
        ], $printer);
    }

    public function lateOrders(CarbonInterface $from, CarbonInterface $to, User $printer): ReportDocument
    {
        $orders = ProductionOrder::query()
            ->with(['customer', 'product'])
            ->late()
            ->whereDate('deadline_date', '>=', $from->toDateString())
            ->whereDate('deadline_date', '<=', $to->toDateString())
            ->orderBy('deadline_date')
            ->orderBy('id')
            ->get();

        $rows = $orders->map(fn (ProductionOrder $order): array => [
            $order->number,
            $order->customer->name,
            $order->product->name,
            $order->quantity,
            $order->order_date,
            $order->deadline_date,
            abs($order->daysLeft()),
            $order->status->label(),
        ])->all();

        $average = $orders->isEmpty() ? null : $orders->avg(fn (ProductionOrder $order): int => abs($order->daysLeft()));

        return $this->document('order-terlambat', 'Laporan Order Terlambat', $this->periodMeta('Periode tenggat', $from, $to) + [
            'Jumlah order terlambat' => (string) $orders->count(),
            'Rata-rata keterlambatan' => $average === null ? '-' : number_format((float) $average, 1, ',', '.').' hari',
        ], [
            new ReportTable('Order terlambat', [
                ReportColumn::text('Order'),
                ReportColumn::text('Pelanggan'),
                ReportColumn::text('Produk'),
                ReportColumn::integer('Jumlah (pasang)'),
                ReportColumn::date('Tanggal masuk'),
                ReportColumn::date('Tenggat'),
                ReportColumn::integer('Terlambat (hari)'),
                ReportColumn::text('Status'),
            ], $rows, 'Order yang belum selesai dan tenggatnya sudah lewat pada hari laporan dicetak, dengan tenggat di dalam periode.', empty: 'Tidak ada order terlambat dengan tenggat pada periode ini.'),
        ], $printer);
    }

    public function ordersRecap(CarbonInterface $from, CarbonInterface $to, User $printer): ReportDocument
    {
        $orders = ProductionOrder::query()
            ->with('customer')
            ->whereDate('order_date', '>=', $from->toDateString())
            ->whereDate('order_date', '<=', $to->toDateString())
            ->get();

        $perStatus = collect(OrderStatus::cases())->map(fn (OrderStatus $status): array => [
            $status->label(),
            $orders->where('status', $status)->count(),
            (int) $orders->where('status', $status)->sum('quantity'),
        ])->all();

        $perCustomer = $orders->groupBy('customer_id')->map(function (Collection $group): array {
            $row = [$group->first()->customer->name, $group->count()];

            foreach (OrderStatus::cases() as $status) {
                $row[] = $group->where('status', $status)->count();
            }

            $row[] = (int) $group->sum('quantity');

            return $row;
        })->sortBy(0)->values()->all();

        $sumColumn = fn (array $rows, int $index): int => (int) array_sum(array_column($rows, $index));

        return $this->document('rekap-order', 'Laporan Rekap Order', $this->periodMeta('Periode tanggal masuk', $from, $to) + [
            'Jumlah order' => (string) $orders->count(),
        ], [
            new ReportTable('Rekap per status', [
                ReportColumn::text('Status'),
                ReportColumn::integer('Jumlah order'),
                ReportColumn::integer('Jumlah (pasang)'),
            ], $perStatus, totals: ['Total', $sumColumn($perStatus, 1), $sumColumn($perStatus, 2)], empty: 'Tidak ada order yang masuk pada periode ini.'),
            new ReportTable('Rekap per pelanggan', [
                ReportColumn::text('Pelanggan'),
                ReportColumn::integer('Jumlah order'),
                ReportColumn::integer('Antri'),
                ReportColumn::integer('Dikerjakan'),
                ReportColumn::integer('Selesai'),
                ReportColumn::integer('Dibatalkan'),
                ReportColumn::integer('Jumlah (pasang)'),
            ], $perCustomer, totals: $perCustomer === [] ? null : ['Total', ...array_map(fn (int $index): int => $sumColumn($perCustomer, $index), [1, 2, 3, 4, 5, 6])], empty: 'Tidak ada order yang masuk pada periode ini.'),
        ], $printer);
    }

    public function evaluationHistory(CarbonInterface $from, CarbonInterface $to, User $printer): ReportDocument
    {
        $evaluations = Evaluation::query()
            ->with(['evaluator', 'approver'])
            ->whereDate('evaluated_at', '>=', $from->toDateString())
            ->whereDate('evaluated_at', '<=', $to->toDateString())
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->get();

        $rows = $evaluations->map(fn (Evaluation $evaluation): array => [
            $evaluation->name,
            $evaluation->evaluated_at,
            $evaluation->trigger->label(),
            $evaluation->status->label(),
            $evaluation->order_count,
            $evaluation->top_n,
            $evaluation->evaluator?->name ?? 'Sistem',
            $evaluation->approver?->name ?? '',
            $evaluation->applied_at,
        ])->all();

        return $this->document('riwayat-evaluasi', 'Laporan Riwayat Evaluasi', $this->periodMeta('Periode waktu hitung', $from, $to) + [
            'Jumlah evaluasi' => (string) $evaluations->count(),
        ], [
            new ReportTable('Riwayat evaluasi', [
                ReportColumn::text('Evaluasi'),
                ReportColumn::dateTime('Waktu hitung'),
                ReportColumn::text('Pemicu'),
                ReportColumn::text('Status'),
                ReportColumn::integer('Order dihitung'),
                ReportColumn::integer('Prioritas utama'),
                ReportColumn::text('Dihitung oleh'),
                ReportColumn::text('Disetujui oleh'),
                ReportColumn::dateTime('Diterapkan'),
            ], $rows, empty: 'Tidak ada evaluasi pada periode ini.'),
        ], $printer);
    }

    /**
     * @param  array<string, string>  $meta
     * @param  array<int, ReportTable>  $tables
     */
    protected function document(string $slug, string $title, array $meta, array $tables, User $printer): ReportDocument
    {
        return new ReportDocument($slug, $title, $meta, $tables, CompanyProfile::load(), $printer->name, now());
    }

    /**
     * @return array<string, string>
     */
    protected function periodMeta(string $label, CarbonInterface $from, CarbonInterface $to): array
    {
        return [$label => $from->format('d/m/Y').' sampai '.$to->format('d/m/Y')];
    }

    /**
     * @return array<string, string>
     */
    protected function evaluationMeta(Evaluation $evaluation): array
    {
        $meta = [
            'Evaluasi' => $evaluation->name,
            'Waktu hitung' => $evaluation->evaluated_at->format('d/m/Y H:i'),
            'Pemicu' => $evaluation->trigger->label(),
            'Status' => $evaluation->status->label(),
            'Order dihitung' => (string) $evaluation->order_count,
            'Prioritas utama' => $evaluation->top_n.' order teratas',
            'Dihitung oleh' => $evaluation->evaluator?->name ?? 'Sistem',
        ];

        if ($evaluation->approver !== null) {
            $meta['Disetujui oleh'] = $evaluation->approver->name.', '.$evaluation->approved_at->format('d/m/Y H:i');
        }

        return $meta;
    }

    /**
     * The criteria saved with the evaluation, without the order value criterion for a user who may not see rupiah.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function visibleCriteria(Evaluation $evaluation, User $viewer): array
    {
        return collect($evaluation->criteria_snapshot)
            ->reject(fn (array $criterion): bool => $criterion['value_source'] === ValueSource::OrderValue->value && ! $viewer->canSeeOrderValue())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, EvaluationItem>
     */
    protected function items(Evaluation $evaluation): Collection
    {
        return $evaluation->items()->get()->values();
    }
}
