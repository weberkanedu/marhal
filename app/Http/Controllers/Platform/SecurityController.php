<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Security\ResolveSecurityAlert;
use App\Http\Controllers\Controller;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Support\Security\SecuritySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform → Güvenlik: bütün acentelere uygulanan oturum / cihaz kuralları (SecuritySettings) ve
 * şüpheli kullanım uyarısının kapatılması ("Kullanıcıyı doğrula", ResolveSecurityAlert).
 */
class SecurityController extends Controller
{
    public function index(SecuritySettings $settings): Response
    {
        return Inertia::render('platform/Security', [
            'settings' => $settings->all(),
            'mailReady' => SecuritySettings::mailReady(),
        ]);
    }

    public function update(Request $request, SecuritySettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'single_session' => ['required', 'boolean'],
            'device_limit' => ['required', 'integer', 'between:1,10'],
            'new_device_code' => ['required', 'boolean'],
            'admin_two_factor' => ['required', 'boolean'],
            'suspicious_alerts' => ['required', 'boolean'],
            'device_limit_alert' => ['required', 'boolean'],
        ]);

        $settings->save($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Güvenlik kuralları kaydedildi.']);

        return back();
    }

    public function resolve(Request $request, SecurityAlert $alert, ResolveSecurityAlert $resolve): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolve->handle($alert, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$alert->user->name}: bütün oturumlar ve cihazlar sıfırlandı; şifresiyle yeniden girecek.",
        ]);

        return back();
    }
}
