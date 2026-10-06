<?php

namespace App\Support\Security;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Platform → Güvenlik kuralları (bütün acentelere uygulanır). Tek kaynak: giriş kuralı, ara katmanlar ve
 * platform ekranı buradan okur. Kayıt yoksa varsayılanlar geçerlidir.
 *
 * "Yeni cihazda e-posta kodu" e-posta servisi bağlanana kadar açılamaz (10d-2).
 */
class SecuritySettings
{
    private const KEY = 'security';

    private const CACHE = 'platform:security';

    /** Kısa sürede çok cihaz / ağ kuralının penceresi (saat) ve eşiği. */
    public const WINDOW_HOURS = 2;

    public const SUSPICIOUS_THRESHOLD = 4;

    /** @var array{single_session: bool, device_limit: int, new_device_code: bool, admin_two_factor: bool, suspicious_alerts: bool, device_limit_alert: bool} */
    public const DEFAULTS = [
        'single_session' => true,
        'device_limit' => 3,
        'new_device_code' => false,
        'admin_two_factor' => false,
        'suspicious_alerts' => true,
        'device_limit_alert' => true,
    ];

    /**
     * @return array{single_session: bool, device_limit: int, new_device_code: bool, admin_two_factor: bool, suspicious_alerts: bool, device_limit_alert: bool}
     */
    public function all(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(self::CACHE, fn () => PlatformSetting::query()->find(self::KEY)->value ?? []);

        $merged = [...self::DEFAULTS, ...array_intersect_key($stored, self::DEFAULTS)];

        return [
            'single_session' => (bool) $merged['single_session'],
            'device_limit' => max(1, min(10, (int) $merged['device_limit'])),
            'new_device_code' => (bool) $merged['new_device_code'] && self::mailReady(),
            'admin_two_factor' => (bool) $merged['admin_two_factor'],
            'suspicious_alerts' => (bool) $merged['suspicious_alerts'],
            'device_limit_alert' => (bool) $merged['device_limit_alert'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => [...$this->all(), ...array_intersect_key($values, self::DEFAULTS)]],
        );
        Cache::forget(self::CACHE);
    }

    /**
     * E-posta gerçekten gidiyor mu? (bugün MAIL_MAILER=log; e-posta kodu ancak servis bağlanınca açılabilir)
     */
    public static function mailReady(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
