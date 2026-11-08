<?php

use App\Models\Assessment;
use App\Models\AssessmentHistory;
use App\Models\Criterion;
use App\Models\Customer;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\AssessmentWriter;
use Database\Seeders\CriteriaSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(CriteriaSeeder::class);
    $this->criteria = Criterion::query()->get()->keyBy('code');
    $this->writer = app(AssessmentWriter::class);
    $this->user = User::factory()->manager()->create();
    $this->order = ProductionOrder::factory()->for(Customer::factory()->urgency(3))->create();
});

it('stores a manual value with who and when', function (): void {
    $assessment = $this->writer->setManual($this->order, $this->criteria['C3'], 75, $this->user);

    expect((float) $assessment->raw_value)->toBe(75.0)
        ->and($assessment->updated_by)->toBe($this->user->id)
        ->and($assessment->value_updated_at->isToday())->toBeTrue()
        ->and($assessment->is_overridden)->toBeFalse();
});

it('keeps one row per order and criterion when the value is set again', function (): void {
    $this->writer->setManual($this->order, $this->criteria['C3'], 75, $this->user);
    $this->writer->setManual($this->order, $this->criteria['C3'], 60, $this->user);

    expect(Assessment::query()->where('production_order_id', $this->order->id)->count())->toBe(1)
        ->and((float) Assessment::query()->first()->raw_value)->toBe(60.0);
});

it('writes a history row with the old and the new value only when the value changes', function (): void {
    $this->writer->setManual($this->order, $this->criteria['C3'], 75, $this->user);
    $this->writer->setManual($this->order, $this->criteria['C3'], 60, $this->user);
    $this->writer->setManual($this->order, $this->criteria['C3'], 60, $this->user);

    $rows = AssessmentHistory::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->old_value)->toBeNull()
        ->and((float) $rows[0]->new_value)->toBe(75.0)
        ->and((float) $rows[1]->old_value)->toBe(75.0)
        ->and((float) $rows[1]->new_value)->toBe(60.0)
        ->and($rows[1]->changed_by)->toBe($this->user->id);
});

it('refreshes the update time when the same value is confirmed again', function (): void {
    $assessment = $this->writer->setManual($this->order, $this->criteria['C3'], 75, $this->user);
    $assessment->update(['value_updated_at' => now()->subDays(6)]);

    $this->writer->setManual($this->order, $this->criteria['C3'], 75, $this->user);

    expect($assessment->fresh()->value_updated_at->isToday())->toBeTrue()
        ->and(AssessmentHistory::count())->toBe(1);
});

it('clears a value and records it in the history', function (): void {
    $this->writer->setManual($this->order, $this->criteria['C4'], 24, $this->user);
    $this->writer->setManual($this->order, $this->criteria['C4'], null, $this->user);

    $last = AssessmentHistory::query()->latest('id')->first();

    expect(Assessment::query()->first()->raw_value)->toBeNull()
        ->and((float) $last->old_value)->toBe(24.0)
        ->and($last->new_value)->toBeNull();
});

it('does not create a row when an empty value is set on a criterion that was never filled', function (): void {
    $this->writer->setManual($this->order, $this->criteria['C4'], null, $this->user);

    expect(Assessment::count())->toBe(0)
        ->and(AssessmentHistory::count())->toBe(0);
});

it('keeps percentages between 0 and 100', function (float $value, bool $valid): void {
    $attempt = fn () => $this->writer->setManual($this->order, $this->criteria['C3'], $value, $this->user);

    if ($valid) {
        expect($attempt())->toBeInstanceOf(Assessment::class);
    } else {
        expect($attempt)->toThrow(ValidationException::class);
        expect(Assessment::count())->toBe(0);
    }
})->with([
    'minus' => [-1, false],
    'zero' => [0, true],
    'decimal inside' => [79.5, true],
    'one hundred' => [100, true],
    'just over' => [100.01, false],
]);

it('does not accept negative hours or negative rupiah', function (): void {
    expect(fn () => $this->writer->setManual($this->order, $this->criteria['C4'], -0.5, $this->user))->toThrow(ValidationException::class);

    $unpriced = ProductionOrder::factory()->create(['unit_price' => null]);

    expect(fn () => $this->writer->setManual($unpriced, $this->criteria['C6'], -100, $this->user))->toThrow(ValidationException::class);
    expect($this->writer->setManual($this->order, $this->criteria['C4'], 0, $this->user))->toBeInstanceOf(Assessment::class);
});

it('refuses to write a criterion that is filled automatically', function (string $code): void {
    expect(fn () => $this->writer->setManual($this->order, $this->criteria[$code], 5, $this->user))
        ->toThrow(ValidationException::class);

    expect(Assessment::count())->toBe(0);
})->with(['C1', 'C2', 'C7']);

it('refuses to write the order value while the order has a unit price', function (): void {
    expect(fn () => $this->writer->setManual($this->order, $this->criteria['C6'], 5000000, $this->user))
        ->toThrow(ValidationException::class);
});

it('allows writing the order value when the order has no unit price', function (): void {
    $unpriced = ProductionOrder::factory()->create(['unit_price' => null]);

    $assessment = $this->writer->setManual($unpriced, $this->criteria['C6'], 80000000, $this->user);

    expect((float) $assessment->raw_value)->toBe(80000000.0);
});

it('overrides the urgency of one order with a reason and keeps the customer untouched', function (): void {
    $assessment = $this->writer->override($this->order, $this->criteria['C2'], 5, 'Pelanggan strategis', $this->user);

    expect($assessment->is_overridden)->toBeTrue()
        ->and((float) $assessment->raw_value)->toBe(5.0)
        ->and($assessment->override_reason)->toBe('Pelanggan strategis')
        ->and($this->order->customer->fresh()->urgency_level)->toBe(3)
        ->and(AssessmentHistory::count())->toBe(1);
});

it('requires a reason to override the urgency', function (?string $reason): void {
    expect(fn () => $this->writer->override($this->order, $this->criteria['C2'], 5, $reason, $this->user))
        ->toThrow(ValidationException::class);

    expect(Assessment::count())->toBe(0);
})->with([null, '', '   ']);

it('limits the override to a whole urgency from 1 to 5 and to the urgency criterion', function (): void {
    foreach ([0, 6, 2.5] as $bad) {
        expect(fn () => $this->writer->override($this->order, $this->criteria['C2'], $bad, 'Alasan', $this->user))
            ->toThrow(ValidationException::class);
    }

    expect(fn () => $this->writer->override($this->order, $this->criteria['C3'], 50, 'Alasan', $this->user))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->writer->override($this->order, $this->criteria['C2'], 4, str_repeat('a', 256), $this->user))
        ->toThrow(ValidationException::class);
});

it('goes back to the urgency of the customer when the override is cleared', function (): void {
    $this->writer->override($this->order, $this->criteria['C2'], 5, 'Pelanggan strategis', $this->user);
    $this->writer->clearOverride($this->order, $this->criteria['C2'], $this->user);

    $assessment = Assessment::query()->first();
    $last = AssessmentHistory::query()->latest('id')->first();

    expect($assessment->is_overridden)->toBeFalse()
        ->and($assessment->raw_value)->toBeNull()
        ->and($assessment->override_reason)->toBeNull()
        ->and((float) $last->old_value)->toBe(5.0)
        ->and($last->new_value)->toBeNull();
});

it('does nothing when clearing an override that does not exist', function (): void {
    $this->writer->clearOverride($this->order, $this->criteria['C2'], $this->user);

    expect(Assessment::count())->toBe(0)
        ->and(AssessmentHistory::count())->toBe(0);
});

it('replaces an earlier override and records the change', function (): void {
    $this->writer->override($this->order, $this->criteria['C2'], 5, 'Pertama', $this->user);
    $this->writer->override($this->order, $this->criteria['C2'], 4, 'Kedua', $this->user);

    $last = AssessmentHistory::query()->latest('id')->first();

    expect(Assessment::count())->toBe(1)
        ->and(Assessment::query()->first()->override_reason)->toBe('Kedua')
        ->and((float) $last->old_value)->toBe(5.0)
        ->and((float) $last->new_value)->toBe(4.0);
});
