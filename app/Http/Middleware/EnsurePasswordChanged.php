<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Geçici şifreyle giriş yapan kullanıcıyı, şifresini değiştirene kadar
 * güvenlik ayarları sayfasına yönlendirir.
 */
class EnsurePasswordChanged
{
    /**
     * Şifre değiştirme akışı için serbest bırakılan rotalar.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs(...self::ALLOWED)) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Devam etmeden önce geçici şifrenizi değiştirin.']);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
