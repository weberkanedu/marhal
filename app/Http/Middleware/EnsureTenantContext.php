<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acente ekranları için: kullanıcının acentesini istek bağlamına koyar,
 * pasif kullanıcıyı / askıdaki acenteyi içeri almaz.
 */
class EnsureTenantContext
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

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

        return $next($request);
    }
}
