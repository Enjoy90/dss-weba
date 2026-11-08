<?php

namespace App\Enums;

enum ValueSource: string
{
    case Deadline = 'deadline';
    case CustomerUrgency = 'customer_urgency';
    case OrderValue = 'order_value';
    case Waiting = 'waiting';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Deadline => 'Otomatis: sisa hari menuju tenggat',
            self::CustomerUrgency => 'Otomatis: urgensi pelanggan',
            self::OrderValue => 'Otomatis: nilai order',
            self::Waiting => 'Otomatis: lama menunggu',
            self::Manual => 'Manual',
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $source) {
            $options[$source->value] = $source->label();
        }

        return $options;
    }
}
