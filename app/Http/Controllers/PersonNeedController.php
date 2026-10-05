<?php

namespace App\Http\Controllers;

use App\Actions\Persons\SavePersonNeeds;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Yolcunun ihtiyaç profili ve sağlık verisi açık rızası (yolcu sayfasındaki "İhtiyaçlar" kartı).
 * Yalnız yolcuyu düzenleyebilen personel (yönetici / operasyon) değiştirebilir.
 */
class PersonNeedController extends Controller
{
    public function update(Request $request, Person $person, SavePersonNeeds $needs): RedirectResponse
    {
        Gate::authorize('update', $person);

        $data = $request->validate([
            'items' => ['present', 'array', 'max:30'],
            'items.*.type_id' => ['required', 'uuid'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ], [], ['items.*.note' => 'not']);

        $needs->save($person, $data['items'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'İhtiyaçlar kaydedildi.']);

        return back();
    }

    public function consent(Request $request, Person $person, SavePersonNeeds $needs): RedirectResponse
    {
        Gate::authorize('update', $person);

        $granted = $request->validate(['granted' => ['required', 'boolean']])['granted'];

        if ($granted) {
            $needs->grantConsent($person, $request->user());
            $message = 'Açık rıza kaydedildi; ihtiyaç bilgisi girilebilir.';
        } else {
            $needs->revokeConsent($person);
            $message = 'Açık rıza geri alındı; ihtiyaç bilgileri silindi.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
