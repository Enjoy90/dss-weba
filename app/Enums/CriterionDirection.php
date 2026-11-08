<?php

namespace App\Enums;

enum CriterionDirection: string
{
    case HigherFirst = 'higher_first';
    case LowerFirst = 'lower_first';

    public function label(): string
    {
        return match ($this) {
            self::HigherFirst => 'Makin besar didahulukan',
            self::LowerFirst => 'Makin kecil didahulukan',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $direction) {
            $options[$direction->value] = $direction->label();
        }

        return $options;
    }
}
