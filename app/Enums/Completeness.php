<?php

namespace App\Enums;

enum Completeness: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
    case Stale = 'stale';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Lengkap',
            self::Incomplete => 'Belum lengkap',
            self::Stale => 'Perlu diperbarui',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Complete => 'success',
            self::Incomplete => 'danger',
            self::Stale => 'warning',
        };
    }
}
