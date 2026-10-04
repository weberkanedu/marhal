<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoomType: string
{
    use HasOptions;

    case Double = '2li';
    case Triple = '3lu';
    case Quad = '4lu';
    case Quint = '5li';

    public function label(): string
    {
        return match ($this) {
            self::Double => '2 kişilik',
            self::Triple => '3 kişilik',
            self::Quad => '4 kişilik',
            self::Quint => '5 kişilik',
        };
    }
}
