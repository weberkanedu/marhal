<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Acentenin Marhal'a yaptığı abonelik ödemesinin yöntemi (yolcu ödemeleri için PaymentMethod ayrıdır).
 */
enum SubscriptionPaymentMethod: string
{
    use HasOptions;

    case Transfer = 'havale';
    case Card = 'kart';
    case Other = 'diger';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Havale / EFT',
            self::Card => 'Kredi kartı',
            self::Other => 'Diğer',
        };
    }
}
