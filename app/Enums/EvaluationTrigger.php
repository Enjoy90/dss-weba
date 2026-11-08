<?php

namespace App\Enums;

enum EvaluationTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Terjadwal',
        };
    }
}
