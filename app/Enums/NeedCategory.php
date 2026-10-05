<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum NeedCategory: string
{
    use HasOptions;

    case Mobility = 'hareket';
    case Health = 'saglik';
    case Diet = 'beslenme';
    case Other = 'diger';

    public function label(): string
    {
        return match ($this) {
            self::Mobility => 'Hareket',
            self::Health => 'Sağlık',
            self::Diet => 'Beslenme',
            self::Other => 'Diğer',
        };
    }
}
