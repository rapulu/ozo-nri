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
    case Oba = 'Oba';
    case Prince = 'Prince';
    case Ide = 'Ide';
    case Alhaji = 'Alhaji';
    case Prof = 'Prof.';
    case Justice = 'Justice';
    case ChiefDr = 'Chief Dr.';
    case PrinceDr = 'Prince Dr.';
    case IchieDr = 'Ichie Dr.';
    case ChiefBarr = 'Chief Barr.';
    case ObaBarr = 'Oba Barr.';
    case ChiefJustice = 'Chief Justice';

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
