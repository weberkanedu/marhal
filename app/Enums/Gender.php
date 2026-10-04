<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Gender: string
{
    use HasOptions;

    case Male = 'erkek';
    case Female = 'kadin';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Erkek',
            self::Female => 'Kadın',
        };
    }
}
