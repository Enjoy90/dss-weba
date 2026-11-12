<?php

use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\Evaluation;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\Validation\ManualCalculationWorkbook;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Facades\Artisan;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $criteria = Criterion::query()->get()->keyBy('code');
    $this->manager = User::factory()->manager()->create();

    // Orders with different deadlines, urgency, value and manual values, plus one with no manual values at all.
    foreach ([[1, 5, 100, 4, 90], [5, 3, 60, 30, 40], [12, 2, 30, 100, 10], [20, 4, 80, 50, 70]] as $n => [$due, $urgency, $material, $hours, $progress]) {
        $order = ProductionOrder::factory()->dueInDays($due)->create(['customer_id' => Customer::factory()->urgency($urgency), 'unit_price' => 50000 * ($n + 1), 'quantity' => 300, 'order_date' => today()->subDays($n * 3)]);

        foreach (['C3' => $material, 'C4' => $hours, 'C5' => $progress] as $code => $value) {
            Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => $criteria[$code]->id, 'raw_value' => $value, 'value_updated_at' => now()]);
        }
    }

    // Fixed price and quantity: the highest order value stays at scale 4, so a column maximum below 5 is always
    // there and a normalisation that divides by 5 instead of the maximum cannot go unnoticed.
    ProductionOrder::factory()->dueInDays(9)->create(['customer_id' => Customer::factory()->urgency(2), 'unit_price' => 10000, 'quantity' => 100]);

    $this->evaluation = app(EvaluationService::class)->run($this->manager, acknowledgeWarnings: true);
    $this->path = tempnam(sys_get_temp_dir(), 'manual').'.xlsx';
});

afterEach(function (): void {
    @unlink($this->path);
});

/**
 * @return array<string, array<int, array<int, mixed>>> rows per sheet
 */
function manualSheets(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = array_map(fn ($cell) => $cell instanceof FormulaCell ? $cell->getValue() : $cell->getValue(), $row->getCells());
        }
    }

    $reader->close();

    return $sheets;
}

it('puts only the raw values and the scale tables in, and computes everything else with formulas', function (): void {
    app(ManualCalculationWorkbook::class)->write($this->evaluation, $this->path);
    $sheets = manualSheets($this->path);

    expect(array_keys($sheets))->toBe(['Hasil', 'Bobot', 'Tabel skala', 'Nilai asli', 'Skala', 'Normalisasi', 'Skor'])
        ->and($sheets['Nilai asli'])->toHaveCount(6)
        ->and($sheets['Bobot'][1][1])->toEqual(0.27)
        ->and($sheets['Skala'][1][1])->toStartWith('=IF(')->toContain('SUMPRODUCT')
        ->and($sheets['Normalisasi'][1][1])->toStartWith('=Skala!B2/MAX(Skala!B$2:B$6)')
        ->and($sheets['Skor'][1][8])->toBe('=SUM(B2:H2)')
        ->and($sheets['Hasil'][1][1])->toBe('=Skor!I2')
        ->and($sheets['Hasil'][1][2])->toEqual((float) $this->evaluation->items()->reorder('system_rank')->first()->score)
        ->and(array_column(array_slice($sheets['Hasil'], 1, 5), 4))->toBe([1, 2, 3, 4, 5]);

    // The order without manual values has empty cells, which the formula turns into scale 1.
    $empty = collect($sheets['Nilai asli'])->first(fn (array $row): bool => in_array('', $row, true));
    expect($empty)->not->toBeNull();
});

it('writes the scale table with open bounds and the scale of each range', function (): void {
    app(ManualCalculationWorkbook::class)->write($this->evaluation, $this->path);
    $table = manualSheets($this->path)['Tabel skala'];

    // C1: below 2 days is scale 5 (open lower bound), 15 days and more is scale 1 (open upper bound).
    expect($table[2][0])->toEqual(-1.0E15)->and($table[2][1])->toEqual(2.0)->and($table[2][2])->toEqual(5)
        ->and($table[6][0])->toEqual(15.0)->and($table[6][1])->toEqual(1.0E15)->and($table[6][2])->toEqual(1);
});

it('names the column letters past Z', function (): void {
    expect(ManualCalculationWorkbook::column(1))->toBe('A')
        ->and(ManualCalculationWorkbook::column(26))->toBe('Z')
        ->and(ManualCalculationWorkbook::column(27))->toBe('AA');
});

it('reproduces every system score within 0.0001 when a spreadsheet program computes the formulas', function (): void {
    $soffice = getenv('SOFFICE') ?: 'C:\\Program Files\\LibreOffice\\program\\soffice.exe';

    if (! is_file($soffice)) {
        $this->markTestSkipped('LibreOffice tidak terpasang.');
    }

    $exit = Artisan::call('validation:manual-excel', ['path' => $this->path, '--verify' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Order dibandingkan: 5.')->toContain('Lolos');
});

it('says so when there is no evaluation yet', function (): void {
    $this->evaluation->items()->delete();
    Evaluation::query()->delete();

    expect(Artisan::call('validation:manual-excel', ['path' => $this->path]))->toBe(1)
        ->and(Artisan::output())->toContain('Belum ada evaluasi');
});
