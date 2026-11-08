<?php

namespace App\Enums;

enum OrderPriority: string
{
    case Normal = 'normal';
    case Top = 'top';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Top => 'Prioritas Utama',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Top => 'warning',
        };
    }
}
