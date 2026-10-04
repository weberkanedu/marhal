<?php

namespace App\Http\Controllers;

use App\Actions\Flights\FlightPassengers;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Uçuşa yolcu ekleme (gruplarla toplu veya tek tek), PNR / bilet no girme, çıkarma.
 */
class FlightPassengerController extends Controller
{
    public function store(Request $request, Flight $flight, FlightPassengers $rules): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $data = $request->validate([
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', Rule::exists('groups', 'id')->where('tour_id', $flight->tour_id)->whereNull('deleted_at')],
            'registration_ids' => ['nullable', 'array'],
            'registration_ids.*' => ['uuid'],
        ]);

        $registrations = $rules->ofGroups($flight, $data['group_ids'] ?? [])
            // Tek tek seçilenler: acente kapsamı sayesinde başka acentenin kaydı bulunmaz.
            ->merge(Registration::query()->whereIn('id', $data['registration_ids'] ?? [])->with('person')->get())
            ->unique('id');

        $result = $rules->add($flight, $registrations);

        $message = "{$result['added']} yolcu eklendi.";
        if ($result['skipped'] !== []) {
            $message .= ' Eklenemeyenler: '.collect($result['skipped'])->map(fn (array $s) => "{$s['name']} ({$s['reason']})")->join(', ');
        }

        Inertia::flash('toast', ['type' => $result['skipped'] === [] ? 'success' : 'warning', 'message' => $message]);

        return back();
    }

    public function update(Request $request, FlightPassenger $passenger): RedirectResponse
    {
        Gate::authorize('update', $passenger->flight->tour);

        $data = $request->validate([
            'pnr' => ['nullable', 'string', 'max:20'],
            'ticket_no' => ['nullable', 'string', 'max:30'],
        ], [], ['pnr' => 'PNR', 'ticket_no' => 'bilet no']);

        $passenger->update([
            'pnr' => filled($data['pnr'] ?? null) ? strtoupper(trim($data['pnr'])) : null,
            'ticket_no' => filled($data['ticket_no'] ?? null) ? trim($data['ticket_no']) : null,
        ]);

        return back();
    }

    public function destroy(FlightPassenger $passenger): RedirectResponse
    {
        Gate::authorize('update', $passenger->flight->tour);

        $passenger->delete();

        return back();
    }
}
