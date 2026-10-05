<?php

namespace App\Http\Controllers;

use App\Enums\BadgeSize;
use App\Models\BadgeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente ayarları → Yaka kartı: boy, ön yüz alanları, arka yüz ve dilleri, sağlık notu.
 * Basılan her yaka kartı (tur, grup, tek yolcu) bu ayarla üretilir.
 */
class BadgeSettingController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize('update', BadgeSetting::class);

        $settings = BadgeSetting::current();
        $tenant = $request->user()?->tenant;

        return Inertia::render('badge-settings/Edit', [
            'settings' => [
                'size' => $settings->size->value,
                'fields' => $settings->fields,
                'back_languages' => $settings->back_languages,
                'back_side' => $settings->back_side,
                'health_note' => $settings->health_note,
            ],
            'sizes' => BadgeSize::options(),
            'agency' => ['name' => $tenant?->name, 'phone' => $tenant?->phone],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('update', BadgeSetting::class);

        $data = $request->validate([
            'size' => ['required', Rule::enum(BadgeSize::class)],
            'fields' => ['array'],
            'fields.*' => [Rule::in(BadgeSetting::FIELDS)],
            'back_languages' => ['array', 'min:1'],
            'back_languages.*' => [Rule::in(BadgeSetting::LANGUAGES)],
            'back_side' => ['required', 'boolean'],
            'health_note' => ['required', 'boolean'],
        ], ['back_languages.min' => 'Arka yüz için en az bir dil seçin.'], ['back_languages' => 'arka yüz dilleri']);

        // Sıra sabit kalsın (kartta da bu sırayla görünür).
        $data['fields'] = array_values(array_intersect(BadgeSetting::FIELDS, $data['fields'] ?? []));
        $data['back_languages'] = array_values(array_intersect(BadgeSetting::LANGUAGES, $data['back_languages']));

        BadgeSetting::query()->firstOrNew()->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Yaka kartı ayarı kaydedildi.']);

        return back();
    }
}
