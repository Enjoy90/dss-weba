<?php

namespace App\Services\Validation;

use InvalidArgumentException;

/**
 * System Usability Scale for task P10-05 (target T6: a score of at least 68). Ten statements answered from 1 (strongly
 * disagree) to 5 (strongly agree). Odd statements are positive and count answer - 1, even statements are negative and
 * count 5 - answer. The sum times 2.5 gives a score from 0 to 100.
 */
class SusScore
{
    /**
     * The ten statements in Indonesian, in the standard order.
     */
    public const STATEMENTS = [
        1 => 'Saya ingin sering memakai aplikasi ini.',
        2 => 'Aplikasi ini terlalu rumit tanpa alasan.',
        3 => 'Aplikasi ini mudah dipakai.',
        4 => 'Saya butuh bantuan orang teknis untuk bisa memakai aplikasi ini.',
        5 => 'Fitur-fitur di aplikasi ini menyatu dengan baik.',
        6 => 'Terlalu banyak hal yang tidak konsisten di aplikasi ini.',
        7 => 'Kebanyakan orang akan cepat belajar memakai aplikasi ini.',
        8 => 'Aplikasi ini terasa merepotkan untuk dipakai.',
        9 => 'Saya merasa yakin saat memakai aplikasi ini.',
        10 => 'Saya perlu belajar banyak hal sebelum bisa memakai aplikasi ini.',
    ];

    /**
     * @param  array<int, int>  $answers  ten answers from 1 to 5, statement 1 first
     */
    public function score(array $answers): float
    {
        $answers = array_values($answers);

        if (count($answers) !== 10) {
            throw new InvalidArgumentException('SUS butuh tepat 10 jawaban.');
        }

        $sum = 0;

        foreach ($answers as $i => $answer) {
            if (! is_int($answer) || $answer < 1 || $answer > 5) {
                throw new InvalidArgumentException('Jawaban butir '.($i + 1).' harus bilangan 1 sampai 5.');
            }

            $sum += $i % 2 === 0 ? $answer - 1 : 5 - $answer;
        }

        return $sum * 2.5;
    }

    /**
     * The usual reading of a SUS score (Bangor, Kortum and Miller 2009; average 68).
     */
    public static function grade(float $score): string
    {
        return match (true) {
            $score > 80.3 => 'A (sangat baik)',
            $score >= 68 => 'B atau C (baik, di atas rata-rata)',
            $score >= 51 => 'D (cukup, di bawah rata-rata)',
            default => 'F (kurang)',
        };
    }
}
