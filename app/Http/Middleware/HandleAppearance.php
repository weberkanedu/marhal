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
     * Tema (kullanıcı ayarı; giriş öncesi çerez) kök şablona verilir, böylece sayfa ilk çizimde
     * doğru renklerle gelir. Koyu / açık mod temanın kendisinden gelir.
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

        View::share('colorTheme', $theme);

        return $next($request);
    }
}
