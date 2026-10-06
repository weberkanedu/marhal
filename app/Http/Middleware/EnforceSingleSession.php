<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Security\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tek oturum (10d): kullanıcı başka bir cihazdan giriş yaptıysa bu cihazdaki oturum kapanır ve giriş
 * ekranında nedeni yazar. Geçerli cihaz girişte belirlenir (RecordLogin). Kural Platform → Güvenlik'ten kapatılabilir.
 */
class EnforceSingleSession
{
    public function __construct(private readonly SecuritySettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // Alan, oturumdaki kullanıcı nesnesine her zaman yüklenmemiş olabilir (eksik alan koruması açık).
        $current = $user?->getAttributes()['current_device_id'] ?? null;

        if ($user !== null
            && $current !== null
            && $request->hasSession()
            && (int) $request->session()->get('device_id') !== (int) $current
            && $this->settings->all()['single_session']) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Hesabınıza başka bir cihazdan giriş yapıldı; bu cihazdaki oturum kapatıldı.');
        }

        return $next($request);
    }
}
