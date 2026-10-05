<?php

namespace App\Enums;

enum LevyStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Exempted = 'exempted';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Unpaid->value => 'Unpaid',
            self::Partial->value => 'Partial',
            self::Paid->value => 'Paid',
            self::Exempted->value => 'Exempted',
        ];
    }
}
