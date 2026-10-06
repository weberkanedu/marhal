<?php

namespace App\Enums;

/**
 * Telefonla ön kayıt başvurusunun durumu.
 */
enum SignupStatus: string
{
    case Pending = 'bekliyor';
    case Approved = 'onaylandi';
    case Rejected = 'reddedildi';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Bekliyor',
            self::Approved => 'Onaylandı',
            self::Rejected => 'Reddedildi',
        };
    }
}
