<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antri',
            self::InProgress => 'Dikerjakan',
            self::Done => 'Selesai',
            self::Canceled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Canceled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Queued || $this === self::InProgress;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::Queued->value, self::InProgress->value];
    }
}
