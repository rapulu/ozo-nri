<?php

namespace App\Enums;

enum ArrearReason: string
{
    case Condolence = 'condolence';
    case AnnualDues = 'annual_dues';
    case Fine = 'fine';
    case Welfare = 'welfare';
    case Donation = 'donation';
    case Other = 'other';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Condolence->value => 'Condolence',
            self::AnnualDues->value => 'Annual dues',
            self::Fine->value => 'Fine',
            self::Welfare->value => 'Welfare',
            self::Donation->value => 'Donation',
            self::Other->value => 'Other',
        ];
    }
}
