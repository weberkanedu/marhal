<?php

namespace App\Actions\Security;

use App\Enums\SecurityAlertKind;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Security\SecuritySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Her başarılı girişte (Login olayı) çalışır — hesap paylaşımı koruması (10d):
 *
 * - Cihaz: tarayıcıdaki kalıcı çerezle tanınır (yalnız özeti saklanır); yoksa yeni cihaz kaydı açılır.
 * - Tek oturum: kullanıcının "geçerli cihazı" bu cihaz olur; diğer cihazlardaki oturum bir sonraki istekte
 *   kapanır (EnforceSingleSession). "Beni hatırla" çereziyle şifresiz giriş yalnız geçerli cihazda olur;
 *   başka cihaz şifre girmek zorundadır (yoksa iki kişi birbirini düşürerek aynı hesabı kullanırdı).
 * - Şüpheli kullanım: cihaz sınırı aşıldıysa ya da kısa sürede çok cihazdan / çok ağdan girildiyse uyarı açılır.
 *   Önce uyarılır, giriş engellenmez (kullanıcı kararı 2026-10-07).
 */
class RecordLogin
{
    public const COOKIE = 'marhal_device';

    public const COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public function __construct(private readonly SecuritySettings $settings) {}

    /**
     * @return bool false: giriş reddedildi (hatırlama çereziyle başka cihazdan giriş; şifre gerekir)
     */
    public function handle(User $user, Request $request, bool $viaRemember): bool
    {
        $rules = $this->settings->all();
        $device = $this->device($user, $request);

        if ($rules['single_session'] && $viaRemember && $user->current_device_id !== null && $user->current_device_id !== $device->id) {
            return false;
        }

        $user->forceFill(['current_device_id' => $device->id])->saveQuietly();

        if ($request->hasSession()) {
            $request->session()->put('device_id', $device->id);
        }

        if (! $user->isSuperAdmin()) {
            $this->checkSuspicious($user, $rules);
        }

        return true;
    }

    private function device(User $user, Request $request): UserDevice
    {
        $cookie = $request->cookie(self::COOKIE);
        $token = is_string($cookie) ? $cookie : '';

        if (strlen($token) < 32) {
            $token = Str::random(48);
        }

        Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES, httpOnly: true, sameSite: 'lax');

        $ip = $request->ip();

        /** @var UserDevice $device */
        $device = UserDevice::query()->firstOrNew(['user_id' => $user->id, 'token_hash' => hash('sha256', $token)]);
        $device->fill([
            'label' => self::label((string) $request->userAgent()),
            'last_ip' => $ip,
            'last_network' => self::network($ip),
            'last_seen_at' => now(),
        ])->save();

        return $device;
    }

    /**
     * @param  array{device_limit: int, suspicious_alerts: bool, device_limit_alert: bool}  $rules
     */
    private function checkSuspicious(User $user, array $rules): void
    {
        $devices = $user->devices()->count();

        if ($rules['device_limit_alert'] && $devices > $rules['device_limit']) {
            $this->alert($user, SecurityAlertKind::DeviceLimit, ['devices' => $devices, 'limit' => $rules['device_limit']]);
        }

        if (! $rules['suspicious_alerts']) {
            return;
        }

        $recent = $user->devices()->where('last_seen_at', '>=', now()->subHours(SecuritySettings::WINDOW_HOURS))->get();
        $hours = SecuritySettings::WINDOW_HOURS;

        if ($recent->count() >= SecuritySettings::SUSPICIOUS_THRESHOLD) {
            $this->alert($user, SecurityAlertKind::ManyDevices, ['devices' => $recent->count(), 'hours' => $hours]);
        }

        $networks = $recent->pluck('last_network')->filter()->unique()->count();

        if ($networks >= SecuritySettings::SUSPICIOUS_THRESHOLD) {
            $this->alert($user, SecurityAlertKind::ManyNetworks, ['networks' => $networks, 'hours' => $hours]);
        }
    }

    /**
     * Aynı kullanıcı için aynı türde açık uyarı varsa yenisi açılmaz, ayrıntısı güncellenir.
     *
     * @param  array<string, int>  $details
     */
    private function alert(User $user, SecurityAlertKind $kind, array $details): void
    {
        SecurityAlert::query()->updateOrCreate(
            ['user_id' => $user->id, 'kind' => $kind, 'resolved_at' => null],
            ['tenant_id' => $user->tenant_id, 'details' => $details],
        );
    }

    /**
     * Kaba ağ tanımı: IPv4'te ilk üç bölüm, IPv6'da ilk dört grup (aynı ev / ofis aynı ağ sayılır).
     */
    public static function network(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        return str_contains($ip, ':')
            ? implode(':', array_slice(explode(':', $ip), 0, 4))
            : implode('.', array_slice(explode('.', $ip), 0, 3));
    }

    /**
     * "Chrome · Windows" gibi kısa cihaz adı ("Cihazlarım" listesinde görünür).
     */
    public static function label(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Tarayıcı',
        };

        $os = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone / iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'bilinmeyen sistem',
        };

        return "{$browser} · {$os}";
    }
}
