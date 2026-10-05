<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Aracın gövdesi: koltuk planının çizimi (uzunluk, köşeler) ve önerilen düzen buna göre.
 */
enum VehicleBody: string
{
    use HasOptions;

    case Bus = 'otobus';
    case Midi = 'midibus';
    case Mini = 'minibus';
    case Van = 'van';

    public function label(): string
    {
        return match ($this) {
            self::Bus => 'Otobüs',
            self::Midi => 'Midibüs',
            self::Mini => 'Minibüs',
            self::Van => 'Van / VIP minivan',
        };
    }
}
