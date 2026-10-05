<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';
    case Other = 'other';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Cash->value => 'Cash',
            self::BankTransfer->value => 'Bank transfer',
            self::MobileMoney->value => 'Mobile money',
            self::Cheque->value => 'Cheque',
            self::Other->value => 'Other',
        ];
    }
}
