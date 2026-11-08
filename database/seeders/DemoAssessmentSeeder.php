<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use Illuminate\Database\Seeder;

/**
 * Manual assessment values for the open demo orders. Covers every state the pages must show:
 * complete, incomplete (a value left empty or not filled at all), stale (updated too long ago),
 * an urgency override with its reason, and a value that was changed once so history exists.
 */
class DemoAssessmentSeeder extends Seeder
{
    /**
     * Order number suffix => [C3 material percent, C4 hours left, C5 progress percent, state].
     * A null value stays empty. State "stale" backdates the update, "none" leaves the order untouched.
     *
     * @var array<string, array{0: ?int, 1: ?int, 2: ?int, 3: string}>
     */
    private const VALUES = [
        '0005' => [100, 16, 70, 'fresh'],
        '0006' => [100, 40, 55, 'fresh'],
        '0007' => [90, 24, 80, 'stale'],
        '0008' => [100, 60, 35, 'fresh'],
        '0009' => [80, 12, 85, 'fresh'],
        '0010' => [100, 72, 20, 'stale'],
        '0011' => [100, 30, 0, 'fresh'],
        '0012' => [60, 20, 0, 'fresh'],
        '0013' => [40, null, 0, 'fresh'],
        '0014' => [100, 10, 0, 'fresh'],
        '0015' => [75, 36, 0, 'stale'],
        '0016' => [100, 90, 0, 'fresh'],
        '0017' => [50, 110, 0, 'fresh'],
        '0018' => [null, null, null, 'none'],
        '0019' => [100, 50, 0, 'fresh'],
        '0020' => [80, 28, 0, 'stale'],
        '0021' => [100, 48, 0, 'fresh'],
        '0022' => [20, 100, 0, 'fresh'],
        '0023' => [100, null, 0, 'fresh'],
        '0024' => [60, 80, 0, 'fresh'],
        '0025' => [null, null, null, 'none'],
    ];

    public function run(): void
    {
        $manager = User::query()->where('email', 'manajer@spk.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'supervisor@spk.test')->firstOrFail();

        $criteria = Criterion::query()->whereIn('code', ['C2', 'C3', 'C4', 'C5'])->get()->keyBy('code');
        $writer = app(AssessmentWriter::class);
        $year = today()->format('Y');

        foreach (self::VALUES as $suffix => [$material, $hours, $progress, $state]) {
            $order = ProductionOrder::query()->where('number', "ORD-{$year}-{$suffix}")->first();

            if ($order === null || $state === 'none') {
                continue;
            }

            $writer->setManual($order, $criteria['C3'], $material, $manager);

            if ($suffix === '0005') {
                $writer->setManual($order, $criteria['C5'], 50, $supervisor);
            }

            $writer->setManual($order, $criteria['C4'], $hours, $supervisor);
            $writer->setManual($order, $criteria['C5'], $progress, $supervisor);

            $this->backdate($order, $state === 'stale' ? 5 : (int) $suffix % 3);
        }

        $this->overrideUrgency("ORD-{$year}-0017", $criteria['C2'], 5, 'Pelanggan strategis, diminta langsung oleh pimpinan', $writer, $manager);
        $this->overrideUrgency("ORD-{$year}-0024", $criteria['C2'], 1, 'Pesanan contoh, tidak ada tenggat dari pelanggan', $writer, $manager);
    }

    private function overrideUrgency(string $number, Criterion $criterion, int $value, string $reason, AssessmentWriter $writer, User $by): void
    {
        $order = ProductionOrder::query()->where('number', $number)->first();

        if ($order !== null) {
            $writer->override($order, $criterion, $value, $reason, $by);
        }
    }

    /**
     * Moves the "last updated" time of the manual values back so stale and fresh states are visible.
     */
    private function backdate(ProductionOrder $order, int $days): void
    {
        Assessment::query()
            ->where('production_order_id', $order->id)
            ->where('is_overridden', false)
            ->update(['value_updated_at' => now()->subDays($days)]);
    }
}
