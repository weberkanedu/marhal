<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentType: string
{
    use HasOptions;

    case Collection = 'tahsilat';
    case Refund = 'iade';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Tahsilat',
            self::Refund => 'İade',
        };
    }
}
