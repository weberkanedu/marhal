<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Hazırlık maddesinin türü: elle işaretlenen ya da var olan veriden kendiliğinden dolan.
 * Vize ve Nusuk şimdilik elle; Faz 4'te Nusuk entegrasyonu açılınca kendiliğinden dolacak.
 */
enum ReadinessKind: string
{
    use HasOptions;

    case Manual = 'elle';
    case Passport = 'pasaport';
    case Photo = 'fotograf';
    case Ravza = 'ravza';
    case Visa = 'vize';
    case Nusuk = 'nusuk';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Elle işaretlenir',
            self::Passport => 'Pasaport bilgisinden (6 ay kuralı)',
            self::Photo => 'Yolcu fotoğrafından',
            self::Ravza => 'Ravza (randevu kutusuyla)',
            self::Visa => 'Vize (şimdilik elle)',
            self::Nusuk => 'Nusuk (şimdilik elle)',
        };
    }

    /**
     * Ekranda tıklanmaz; durum yolcu bilgisinden hesaplanır.
     */
    public function isAutomatic(): bool
    {
        return $this === self::Passport || $this === self::Photo;
    }
}
