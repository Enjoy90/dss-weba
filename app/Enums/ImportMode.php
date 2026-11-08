<?php

namespace App\Enums;

enum ImportMode: string
{
    case AddOnly = 'add';
    case AddOrUpdate = 'upsert';

    public function label(): string
    {
        return match ($this) {
            self::AddOnly => 'Tambah order baru saja',
            self::AddOrUpdate => 'Tambah baru dan perbarui order yang nomornya sudah ada',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $mode) {
            $options[$mode->value] = $mode->label();
        }

        return $options;
    }
}
