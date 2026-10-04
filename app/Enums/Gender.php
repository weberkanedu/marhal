<?php

namespace App\Enums;

enum Gender: string
{
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
