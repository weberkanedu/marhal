<?php

namespace App\Http\Middleware;

use App\Enums\ColorTheme;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Açık/koyu mod (çerez) ve renk teması (kullanıcı ayarı; giriş öncesi çerez) kök şablona verilir,
     * böylece sayfa ilk çizimde doğru renklerle gelir.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $cookie = $request->cookie('color_theme');

        $theme = $user->theme
            ?? ColorTheme::tryFrom(is_string($cookie) ? $cookie : '')
            ?? ColorTheme::DEFAULT;

        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('colorTheme', $theme);

        return $next($request);
    }
}
