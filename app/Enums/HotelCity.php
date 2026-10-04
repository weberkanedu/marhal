<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HotelCity: string
{
    use HasOptions;

    case Mecca = 'mekke';
    case Medina = 'medine';
    case Other = 'diger';

    public function label(): string
    {
        return match ($this) {
            self::Mecca => 'Mekke',
            self::Medina => 'Medine',
            self::Other => 'Diğer',
        };
    }
}
