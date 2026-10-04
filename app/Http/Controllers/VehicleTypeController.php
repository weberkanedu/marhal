<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use App\Support\TurkishText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acentenin araç tipleri (koltuk düzeni şablonları). Tura otobüs eklerken seçilir.
 * Düzenleme mevcut otobüsleri etkilemez (otobüs düzeni oluşturulurken kopyalanır).
 */
class VehicleTypeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', VehicleType::class);

        return Inertia::render('vehicle-types/Index', [
            'types' => VehicleType::query()
                ->withCount('buses')
                ->get()
                ->sort(fn (VehicleType $a, VehicleType $b) => TurkishText::compare($a->name, $b->name))
                ->values()
                ->map(fn (VehicleType $type) => [
                    ...$type->only(['id', 'name', 'left_seats', 'right_seats', 'rows', 'back_row_seats', 'door_row', 'notes']),
                    'label' => $type->layout()->label(),
                    'seat_count' => $type->layout()->seatCount(),
                    'buses_count' => $type->buses_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', VehicleType::class);

        $type = VehicleType::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$type->name} eklendi ({$type->layout()->label()})."]);

        return back();
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        Gate::authorize('update', $vehicleType);

        $vehicleType->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Araç tipi kaydedildi. Mevcut otobüslerin koltuk düzeni değişmez.']);

        return back();
    }

    /**
     * Otobüslerde kullanılmış araç tipi de silinebilir; otobüsler kendi düzenini korur.
     */
    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        Gate::authorize('delete', $vehicleType);

        $vehicleType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$vehicleType->name} silindi."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'left_seats' => ['required', 'integer', 'min:1', 'max:2'],
            'right_seats' => ['required', 'integer', 'min:1', 'max:2'],
            'rows' => ['required', 'integer', 'min:1', 'max:20'],
            'back_row_seats' => ['nullable', 'integer', 'min:0', 'max:5'],
            'door_row' => ['nullable', 'integer', 'min:1', 'lte:rows'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'name' => 'ad', 'left_seats' => 'sol koltuk', 'right_seats' => 'sağ koltuk', 'rows' => 'sıra sayısı',
            'back_row_seats' => 'arka sıra', 'door_row' => 'kapı sırası', 'notes' => 'not',
        ]);

        $data['back_row_seats'] = min((int) ($data['back_row_seats'] ?? 0), $data['left_seats'] + $data['right_seats'] + 1);

        return $data;
    }
}
