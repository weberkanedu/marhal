<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RegistrationStatus: string
{
    use HasOptions;

    case Pending = 'on_kayit';
    case Confirmed = 'kesin_kayit';
    case Cancelled = 'iptal';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ön kayıt',
            self::Confirmed => 'Kesin kayıt',
            self::Cancelled => 'İptal',
        };
    }
}
