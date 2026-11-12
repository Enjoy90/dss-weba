<?php

use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationService;
use App\Services\Validation\SpearmanCorrelation;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Support\Facades\Artisan;

it('gives 1 for the same order, -1 for the reverse, and the textbook value in between', function (): void {
    $spearman = new SpearmanCorrelation;

    expect($spearman->compare(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])['rho'])->toBe(1.0)
        ->and($spearman->compare(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], ['a' => 4, 'b' => 3, 'c' => 2, 'd' => 1])['rho'])->toBe(-1.0);

    // Without ties rho = 1 - 6 * sum(d^2) / (n (n^2 - 1)): d = 0, -1, 1, 0, 0 gives 1 - 12 / 120 = 0.9.
    $result = $spearman->compare(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], ['a' => 1, 'b' => 3, 'c' => 2, 'd' => 4, 'e' => 5]);

    expect($result['rho'])->toBe(0.9)->and($result['sum_d2'])->toEqual(2.0)->and($result['n'])->toBe(5);
});

it('gives tied places the average rank', function (): void {
    $result = (new SpearmanCorrelation)->compare(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], ['a' => 1, 'b' => 2, 'c' => 2, 'd' => 4]);

    expect($result['rows']['b']['company'])->toBe(2.5)->and($result['rows']['c']['company'])->toBe(2.5)
        ->and($result['rho'])->toBe(0.9487);
});

it('refuses lists with different orders or fewer than three orders', function (): void {
    expect(fn () => (new SpearmanCorrelation)->compare(['a' => 1, 'b' => 2, 'c' => 3], ['a' => 1, 'b' => 2, 'x' => 3]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new SpearmanCorrelation)->compare(['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2]))->toThrow(InvalidArgumentException::class);
});

it('names the strength of a correlation', function (): void {
    expect(SpearmanCorrelation::strength(0.95))->toBe('sangat kuat')
        ->and(SpearmanCorrelation::strength(0.7))->toBe('kuat')
        ->and(SpearmanCorrelation::strength(0.39))->toBe('lemah');
});

describe('the command', function (): void {
    beforeEach(function (): void {
        $this->seed(CriteriaSeeder::class);
        $criteria = Criterion::query()->get()->keyBy('code');

        foreach ([2, 6, 10, 14, 25] as $due) {
            $order = ProductionOrder::factory()->dueInDays($due)->create(['customer_id' => Customer::factory()->urgency(3), 'unit_price' => 100000, 'quantity' => 100, 'order_date' => today()]);

            foreach (['C3' => 100, 'C4' => 10, 'C5' => 50] as $code => $value) {
                Assessment::factory()->create(['production_order_id' => $order->id, 'criterion_id' => $criteria[$code]->id, 'raw_value' => $value, 'value_updated_at' => now()]);
            }
        }

        $this->evaluation = app(EvaluationService::class)->run(User::factory()->manager()->create());
        $this->csv = tempnam(sys_get_temp_dir(), 'urutan');
    });

    afterEach(function (): void {
        @unlink($this->csv);
    });

    it('writes a template, reads it back filled in, and reports rho', function (): void {
        Artisan::call('validation:spearman', ['--template' => $this->csv]);
        $lines = file($this->csv, FILE_IGNORE_NEW_LINES);

        expect($lines[0])->toBe('Nomor order;Pelanggan;Tenggat;Urutan perusahaan')->and($lines)->toHaveCount(6);

        // The company puts the orders exactly like the system.
        $numbers = $this->evaluation->items()->orderBy('final_rank')->get()->map(fn ($item): string => $item->order_snapshot['number']);
        file_put_contents($this->csv, "Nomor order;Urutan perusahaan\n".$numbers->map(fn (string $number, int $i): string => $number.';'.($i + 1))->implode("\n"));

        expect(Artisan::call('validation:spearman', ['--company' => $this->csv]))->toBe(0)
            ->and(Artisan::output())->toContain('Rho Spearman: 1,0000 (sangat kuat)')->toContain('Sasaran T4 tercapai');
    });

    it('uses the nearest deadline as a stand-in and says it is not company data', function (): void {
        Artisan::call('validation:spearman', ['--by-deadline' => true, '--score-rank' => true]);

        expect(Artisan::output())->toContain('bukan data perusahaan')->toContain('skor SAW saja')->toContain('Rho Spearman: 1,0000');
    });

    it('asks for a source when none is given', function (): void {
        expect(Artisan::call('validation:spearman'))->toBe(1)
            ->and(Artisan::output())->toContain('Pilih sumber urutan');
    });
});
