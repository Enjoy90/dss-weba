<?php

namespace App\Enums;

enum AssessmentSource: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Override = 'override';
    case Empty = 'empty';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Otomatis',
            self::Manual => 'Manual',
            self::Override => 'Ditimpa',
            self::Empty => 'Belum diisi',
        };
    }
}
