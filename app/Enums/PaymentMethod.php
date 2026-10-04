<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'nakit';
    case Transfer = 'havale';
    case CreditCard = 'kredi_karti';
    case Other = 'diger';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::Transfer => 'Havale / EFT',
            self::CreditCard => 'Kredi kartı',
            self::Other => 'Diğer',
        };
    }
}
