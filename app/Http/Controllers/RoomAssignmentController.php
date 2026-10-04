<?php

namespace App\Http\Controllers;

use App\Actions\Rooms\AssignRoom;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Yolcuyu odaya yerleştirme / odadan çıkarma (oda planı ekranından).
 */
class RoomAssignmentController extends Controller
{
    public function store(Request $request, Room $room, AssignRoom $assign): RedirectResponse
    {
        Gate::authorize('update', $room->stay->tour);

        $request->validate(['registration_id' => ['required', 'uuid']]);

        // Acente kapsamı (global scope) sayesinde başka acentenin kaydı bulunamaz → 404.
        $registration = Registration::query()->with('person')->whereKey($request->input('registration_id'))->firstOrFail();

        $assign->handle($room, $registration);

        return back();
    }

    public function destroy(RoomAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment->room->stay->tour);

        $assignment->delete();

        return back();
    }
}
