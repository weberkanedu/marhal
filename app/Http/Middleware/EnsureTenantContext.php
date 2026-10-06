<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Security\SecuritySettings;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acente ekranları için: kullanıcının acentesini istek bağlamına koyar,
 * pasif kullanıcıyı / askıdaki acenteyi içeri almaz.
 *
 * Salt okunur acente (deneme bitti / ödeme ek süresi doldu) her şeyi görür ve indirir ama değişiklik
 * yapamaz; yalnız aşağıdaki işlemler açıktır (paket talebi, geri bildirim). Veri hiç silinmez.
 */
class EnsureTenantContext
{
    /** Salt okunurken de yapılabilen değişiklikler (rota adları). */
    public const READ_ONLY_ALLOWED = ['feedback.store', 'agency.plan-request.store', 'agency.plan-request.destroy'];

    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly SecuritySettings $security,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Hesabınız pasif durumda.']);
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('platform.tenants.index');
        }

        $tenant = $user->tenant;

        abort_if($tenant === null, 403, 'Bu alan yalnızca acente kullanıcıları içindir.');
        abort_unless($tenant->isAccessible(), 403, 'Acente hesabı askıda veya deneme süresi dolmuş.');

        $this->currentTenant->set($tenant);

        // Platform → Güvenlik: "yöneticiler için iki adımlı doğrulama zorunlu" açıksa, kurmamış yönetici önce kurar.
        if ($user->hasRole(UserRole::Admin) && $user->two_factor_confirmed_at === null && $this->security->all()['admin_two_factor']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Güvenlik için yöneticilerin iki adımlı doğrulamayı açması gerekiyor. Lütfen aşağıdan kurun.',
            ]);

            return redirect()->route('security.edit');
        }

        if (! $request->isMethodSafe() && ! $request->routeIs(self::READ_ONLY_ALLOWED) && $tenant->isReadOnly()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Hesabınız salt okunur: bilgileri görebilir ve indirebilirsiniz, değişiklik için paketinizi yenileyin.',
            ]);

            return back();
        }

        return $next($request);
    }
}
