<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Http\Requests\RegistrationRequest;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Yolcunun tura kaydı. Ekranı turun detay sayfasıdır (tours/Show).
 */
class RegistrationController extends Controller
{
    public function store(RegistrationRequest $request, Tour $tour): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $registration = $tour->registrations()->create([
            ...$this->data($request),
            'person_id' => $request->validated('person_id'),
            'currency' => $tour->currency,
            'registered_at' => now(),
        ]);

        $name = Person::whereKey($registration->person_id)->first()?->full_name;
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} tura kaydedildi."]);

        return back();
    }

    public function update(RegistrationRequest $request, Registration $registration): RedirectResponse
    {
        Gate::authorize('update', $registration->tour);

        $registration->update($this->data($request, $registration));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kayıt güncellendi.']);

        return back();
    }

    /**
     * Ödemesi olmayan kayıt tamamen silinebilir (yanlışlıkla eklenen yolcu);
     * ödemesi olan kayıt silinmez, iptal edilir.
     */
    public function destroy(Registration $registration): RedirectResponse
    {
        Gate::authorize('update', $registration->tour);

        if ($registration->payments()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ödemesi olan kayıt silinemez; durumunu "İptal" yapın.']);

            return back();
        }

        $registration->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kayıt tur listesinden çıkarıldı.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(RegistrationRequest $request, ?Registration $current = null): array
    {
        $data = $request->safe()->except('person_id');
        $data['discount'] ??= 0;

        $status = RegistrationStatus::from($data['status']);

        if ($status === RegistrationStatus::Cancelled) {
            $data['cancelled_at'] = $current->cancelled_at ?? now();
        } else {
            $data['cancelled_at'] = null;
            $data['cancel_reason'] = null;
        }

        return $data;
    }
}
