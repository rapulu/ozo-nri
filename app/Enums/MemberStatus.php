<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deceased = 'deceased';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Active->value => 'Active',
            self::Suspended->value => 'Suspended',
            self::Deceased->value => 'Deceased',
        ];
    }
}
