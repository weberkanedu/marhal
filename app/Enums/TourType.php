<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TourType: string
{
    use HasOptions;

    case Umrah = 'umre';
    case Hajj = 'hac';

    public function label(): string
    {
        return match ($this) {
            self::Umrah => 'Umre',
            self::Hajj => 'Hac',
        };
    }
}
