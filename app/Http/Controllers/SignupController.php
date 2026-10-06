<?php

namespace App\Http\Controllers;

use App\Actions\Signup\ReviewSignupRequest;
use App\Actions\Signup\SignupLinks;
use App\Models\SignupRequest;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Tur → ön kayıt linki (oluştur / yenile / kapat) ve gelen başvuruların onayı (personel).
 */
class SignupController extends Controller
{
    public function link(Tour $tour, SignupLinks $links, Request $request): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $links->create($tour, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ön kayıt linki hazır. Kopyalayıp WhatsApp grubuna gönderebilirsiniz.']);

        return back();
    }

    public function close(Tour $tour, SignupLinks $links): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $links->close($tour);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ön kayıt linki kapatıldı; artık başvuru alınmaz.']);

        return back();
    }

    public function approve(Request $request, SignupRequest $signupRequest, ReviewSignupRequest $review): RedirectResponse
    {
        Gate::authorize('update', $signupRequest->tour);

        $review->approve($signupRequest, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Başvuru onaylandı; yolcu tura ön kayıt olarak eklendi.']);

        return back();
    }

    public function reject(Request $request, SignupRequest $signupRequest, ReviewSignupRequest $review): RedirectResponse
    {
        Gate::authorize('update', $signupRequest->tour);

        $review->reject($signupRequest, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Başvuru reddedildi; bilgileri silindi.']);

        return back();
    }
}
