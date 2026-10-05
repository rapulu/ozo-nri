<?php

namespace App\Enums;

enum MemberTitle: string
{
    case Nze = 'Nze';
    case Ozo = 'Ozo';
    case Ichie = 'Ichie';
    case Mazi = 'Mazi';
    case Chief = 'Chief';
    case Dr = 'Dr';
    case Mr = 'Mr';
    case Mrs = 'Mrs';
    case Ms = 'Ms';

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value;
        }

        return $options;
    }
}
