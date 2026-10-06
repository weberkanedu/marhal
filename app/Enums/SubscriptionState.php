<?php

namespace App\Enums;

/**
 * Acentenin abonelik durumu (10b). Saklanmaz, tarihlerden hesaplanır (Tenant::subscriptionState):
 * gece çalışan bir göreve gerek kalmaz. Askıda yalnız platform yöneticisi elle seçerse olur.
 */
enum SubscriptionState: string
{
    case Trial = 'deneme';
    case Active = 'aktif';
    case PastDue = 'gecikmede';
    case ReadOnly = 'salt_okunur';
    case Suspended = 'askida';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Deneme',
            self::Active => 'Aktif',
            self::PastDue => 'Gecikmede',
            self::ReadOnly => 'Salt okunur',
            self::Suspended => 'Askıda',
        };
    }

    /**
     * Tasarımdaki rozet rengi (chip ok / acc / warning / danger).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Trial => 'acc',
            self::PastDue => 'warning',
            self::ReadOnly, self::Suspended => 'danger',
        };
    }
}
