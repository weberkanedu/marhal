<?php

namespace App\Http\Controllers;

use App\Actions\Family\CreateFamilyLink;
use App\Models\FamilyLink;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Yolcunun ailesine aile ekranı linki: personel yolcunun iznini işaretleyip oluşturur, kopyalar, iptal eder.
 */
class FamilyLinkController extends Controller
{
    public function store(Request $request, Registration $registration, CreateFamilyLink $create): RedirectResponse
    {
        Gate::authorize('update', $registration->tour);

        $create->handle($registration, $request->boolean('consent'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aile linki oluşturuldu. Kopyalayıp ailesine gönderebilirsiniz.']);

        return back();
    }

    public function destroy(FamilyLink $link): RedirectResponse
    {
        Gate::authorize('update', $link->registration->tour);

        $link->update(['revoked_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aile linki iptal edildi; eski link artık açılmaz.']);

        return back();
    }
}
