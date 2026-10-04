<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ColorTheme;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Appearance', [
            'theme' => $request->user()?->theme->value ?? ColorTheme::DEFAULT->value,
            'themes' => ColorTheme::options(),
        ]);
    }

    /**
     * Tema kullanıcıya kaydedilir (her cihazda aynı); çerez de yazılır ki çıkış yapıldığında
     * giriş ekranı aynı temada kalsın.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::enum(ColorTheme::class)],
        ]);

        $request->user()?->forceFill(['theme' => $data['theme']])->save();

        Cookie::queue('color_theme', $data['theme'], 60 * 24 * 365);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tema kaydedildi.']);

        return back();
    }
}
