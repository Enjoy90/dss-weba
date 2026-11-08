<?php

namespace App\Enums;

enum RankGroup: string
{
    case Locked = 'locked';
    case Escalated = 'escalated';
    case Regular = 'regular';

    public function label(): string
    {
        return match ($this) {
            self::Locked => 'Terkunci',
            self::Escalated => 'Eskalasi',
            self::Regular => 'Biasa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Locked => 'info',
            self::Escalated => 'danger',
            self::Regular => 'gray',
        };
    }
}
