<?php

namespace App\Http\Controllers;

use App\Actions\Buses\AssignSeat;
use App\Actions\Buses\ClearSeats;
use App\Models\Bus;
use App\Models\Registration;
use App\Models\SeatAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Yolcuyu koltuğa oturtma / kaldırma (koltuk planı ekranından).
 */
class SeatAssignmentController extends Controller
{
    public function store(Request $request, Bus $bus, AssignSeat $assign): RedirectResponse
    {
        Gate::authorize('update', $bus->tour);

        $data = $request->validate([
            'registration_id' => ['required', 'uuid'],
            'seat_no' => ['required', 'integer', 'min:1'],
            'swap' => ['sometimes', 'boolean'],
        ]);

        // Acente kapsamı sayesinde başka acentenin kaydı bulunamaz → 404.
        $registration = Registration::query()->with('person')->whereKey($data['registration_id'])->firstOrFail();

        $assign->handle($bus, $registration, (int) $data['seat_no'], (bool) ($data['swap'] ?? false));

        return back();
    }

    /**
     * "Temizle": otobüsteki bütün yolcuları koltuktan kaldırır.
     */
    public function clear(Bus $bus, ClearSeats $clear): RedirectResponse
    {
        Gate::authorize('update', $bus->tour);

        $count = $clear->handle($bus);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Plan temizlendi: {$count} yolcu koltuktan kaldırıldı."]);

        return back();
    }

    public function destroy(SeatAssignment $seat): RedirectResponse
    {
        Gate::authorize('update', $seat->bus->tour);

        $seat->delete();

        return back();
    }
}
