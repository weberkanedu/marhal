<?php

namespace App\Http\Controllers;

use App\Actions\Rooms\CreateRooms;
use App\Actions\Rooms\UpdateRoom;
use App\Enums\RoomKind;
use App\Models\Room;
use App\Models\TourHotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Konaklamanın odaları (toplu ekleme, düzenleme, silme). Ekranı oda planıdır.
 */
class RoomController extends Controller
{
    public function store(Request $request, TourHotel $stay, CreateRooms $create): RedirectResponse
    {
        Gate::authorize('update', $stay->tour);

        $data = $request->validate([
            'start_no' => ['required', 'string', 'max:20'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'floor' => ['nullable', 'string', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10'],
            'kind' => ['required', Rule::enum(RoomKind::class)],
        ], [], $this->attributes() + ['start_no' => 'oda numarası', 'count' => 'oda sayısı']);

        $rooms = $create->handle($stay, $data['start_no'], (int) $data['count'], (int) $data['capacity'], RoomKind::from($data['kind']), $data['floor'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => count($rooms).' oda eklendi.']);

        return back();
    }

    public function update(Request $request, Room $room, UpdateRoom $update): RedirectResponse
    {
        Gate::authorize('update', $room->stay->tour);

        $data = $request->validate([
            'room_no' => ['required', 'string', 'max:20', Rule::unique('rooms')->where('tour_hotel_id', $room->tour_hotel_id)->ignore($room->id)],
            'floor' => ['nullable', 'string', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10'],
            'kind' => ['required', Rule::enum(RoomKind::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['room_no.unique' => 'Bu otelde aynı numaralı oda zaten var.'], $this->attributes());

        $update->handle($room, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$room->room_no} numaralı oda kaydedildi."]);

        return back();
    }

    /**
     * Oda silinince içindeki yolcular yerleşmemiş listesine döner.
     */
    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('update', $room->stay->tour);

        $room->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$room->room_no} numaralı oda silindi."]);

        return back();
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'room_no' => 'oda numarası',
            'floor' => 'kat',
            'capacity' => 'kişi sayısı',
            'kind' => 'oda türü',
            'notes' => 'not',
        ];
    }
}
