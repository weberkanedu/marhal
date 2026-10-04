<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FlightDirection: string
{
    use HasOptions;

    case Outbound = 'gidis';
    case Return = 'donus';
    case Connection = 'aktarma';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Gidiş',
            self::Return => 'Dönüş',
            self::Connection => 'Aktarma / iç hat',
        };
    }
}
