<?php

namespace App\Enums;

enum LockType: string
{
    case None = 'none';
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak terkunci',
            self::Auto => 'Kunci otomatis (sedang dikerjakan)',
            self::Manual => 'Kunci manual',
        };
    }

    public function isLocked(): bool
    {
        return $this !== self::None;
    }
}
