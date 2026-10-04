<?php

namespace App\Http\Controllers;

use App\Actions\Buses\AssignSeat;
use App\Models\Bus;
use App\Models\Registration;
use App\Models\SeatAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
        ]);

        // Acente kapsamı sayesinde başka acentenin kaydı bulunamaz → 404.
        $registration = Registration::query()->with('person')->whereKey($data['registration_id'])->firstOrFail();

        $assign->handle($bus, $registration, (int) $data['seat_no']);

        return back();
    }

    public function destroy(SeatAssignment $seat): RedirectResponse
    {
        Gate::authorize('update', $seat->bus->tour);

        $seat->delete();

        return back();
    }
}
