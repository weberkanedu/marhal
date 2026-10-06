<?php

namespace App\Enums;

/**
 * Şüpheli kullanım uyarısının türü (10d).
 */
enum SecurityAlertKind: string
{
    case DeviceLimit = 'cihaz_siniri';
    case ManyDevices = 'cok_cihaz';
    case ManyNetworks = 'cok_ag';

    public function label(): string
    {
        return match ($this) {
            self::DeviceLimit => 'Cihaz sınırı aşıldı',
            self::ManyDevices => 'Kısa sürede çok cihazdan giriş',
            self::ManyNetworks => 'Kısa sürede farklı ağlardan giriş',
        };
    }
}
