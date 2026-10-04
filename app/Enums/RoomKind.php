<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Odanın kimlere ayrıldığı: erkek / kadın odasına karşı cinsten kişi yerleşemez;
 * aile odasında herkes en az bir oda arkadaşıyla aile bağıyla bağlı olmalıdır.
 */
enum RoomKind: string
{
    use HasOptions;

    case Male = 'erkek';
    case Female = 'kadin';
    case Family = 'aile';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Erkek',
            self::Female => 'Kadın',
            self::Family => 'Aile',
        };
    }

    public static function forGender(Gender $gender): self
    {
        return $gender === Gender::Male ? self::Male : self::Female;
    }
}
