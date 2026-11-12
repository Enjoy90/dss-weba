<?php

use App\Services\Validation\SusScore;
use Illuminate\Support\Facades\Artisan;

it('scores the best and the worst answers as 100 and 0, and all neutral as 50', function (): void {
    $sus = new SusScore;

    expect($sus->score([5, 1, 5, 1, 5, 1, 5, 1, 5, 1]))->toBe(100.0)
        ->and($sus->score([1, 5, 1, 5, 1, 5, 1, 5, 1, 5]))->toBe(0.0)
        ->and($sus->score([3, 3, 3, 3, 3, 3, 3, 3, 3, 3]))->toBe(50.0)
        ->and($sus->score([4, 2, 4, 2, 4, 2, 4, 2, 4, 2]))->toBe(75.0);
});

it('refuses a wrong number of answers or an answer outside 1 to 5', function (): void {
    expect(fn () => (new SusScore)->score([3, 3, 3]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new SusScore)->score([3, 3, 3, 3, 3, 3, 3, 3, 3, 6]))->toThrow(InvalidArgumentException::class);
});

it('reads the usual grade', function (): void {
    expect(SusScore::grade(85))->toBe('A (sangat baik)')
        ->and(SusScore::grade(68))->toBe('B atau C (baik, di atas rata-rata)')
        ->and(SusScore::grade(55))->toBe('D (cukup, di bawah rata-rata)')
        ->and(SusScore::grade(40))->toBe('F (kurang)');
});

it('writes a template and scores a filled file per respondent and per role', function (): void {
    $csv = tempnam(sys_get_temp_dir(), 'sus');
    Artisan::call('validation:sus', ['--template' => $csv]);

    expect(file_get_contents($csv))->toStartWith('Nama;Peran;Butir 1;Butir 2');

    file_put_contents($csv, "Nama;Peran;B1;B2;B3;B4;B5;B6;B7;B8;B9;B10\nA;Manajer;5;1;5;1;5;1;5;1;5;1\nB;Supervisor;4;2;4;2;4;2;4;2;4;2\n");

    expect(Artisan::call('validation:sus', ['path' => $csv]))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('Rata-rata Manajer: 100,0')->toContain('Rata-rata semua responden: 87,5 dari 2 orang')->toContain('Sasaran T6 tercapai');

    @unlink($csv);
});

it('points to the row with a wrong answer', function (): void {
    $csv = tempnam(sys_get_temp_dir(), 'sus');
    file_put_contents($csv, "A;Manajer;5;1;5;1;5;1;5;1;5;9\n");

    expect(Artisan::call('validation:sus', ['path' => $csv]))->toBe(1)
        ->and(Artisan::output())->toContain('Baris 1: Jawaban butir 10');

    @unlink($csv);
});
