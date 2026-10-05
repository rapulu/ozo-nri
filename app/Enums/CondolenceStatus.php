<?php

namespace App\Enums;

enum CondolenceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Open->value => 'Open',
            self::Closed->value => 'Closed',
        ];
    }
}
