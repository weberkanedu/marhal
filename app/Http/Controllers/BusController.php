<?php

namespace App\Http\Controllers;

use App\Actions\Buses\SaveBus;
use App\Models\Bus;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Turun otobüsleri. Ekranı tur detay sayfasıdır ("Otobüsler" bölümü).
 */
class BusController extends Controller
{
    public function store(Request $request, Tour $tour, SaveBus $save): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $bus = $save->handle($tour, $this->validated($request, $tour));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$bus->name} eklendi ({$bus->layout()->label()})."]);

        return back();
    }

    public function update(Request $request, Bus $bus, SaveBus $save): RedirectResponse
    {
        Gate::authorize('update', $bus->tour);

        $save->handle($bus->tour, $this->validated($request, $bus->tour, $bus), $bus);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$bus->name} kaydedildi."]);

        return back();
    }

    public function destroy(Bus $bus): RedirectResponse
    {
        Gate::authorize('update', $bus->tour);

        $bus->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$bus->name} silindi; yolcuların koltukları boşaldı."]);

        return back();
    }

    /**
     * @return array{name: string, vehicle_type_id: string, plate?: string|null, driver_name?: string|null, driver_phone?: string|null, notes?: string|null, reserved_seats?: list<int>, group_ids?: list<string>}
     */
    private function validated(Request $request, Tour $tour, ?Bus $bus = null): array
    {
        // "1, 2" gibi yazılan ayrılmış koltukları listeye çevir.
        $reserved = $request->input('reserved_seats');
        if (is_string($reserved)) {
            $request->merge(['reserved_seats' => array_values(array_filter(array_map('trim', explode(',', $reserved)), fn (string $s) => $s !== ''))]);
        }

        /** @var array{name: string, vehicle_type_id: string, plate?: string|null, driver_name?: string|null, driver_phone?: string|null, notes?: string|null, reserved_seats?: list<int>, group_ids?: list<string>} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('buses')->where('tour_id', $tour->id)->ignore($bus?->id)],
            'vehicle_type_id' => ['required', 'uuid', Rule::exists('vehicle_types', 'id')->where('tenant_id', $tour->tenant_id)],
            'plate' => ['nullable', 'string', 'max:20'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reserved_seats' => ['nullable', 'array', 'max:10'],
            'reserved_seats.*' => ['integer', 'min:1'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', Rule::exists('groups', 'id')->where('tour_id', $tour->id)->whereNull('deleted_at')],
        ], ['name.unique' => 'Bu turda aynı isimde bir otobüs zaten var.', 'reserved_seats.*.integer' => 'Ayrılan koltuklar numara olmalı (örn. 1, 2).'], [
            'name' => 'otobüs adı', 'vehicle_type_id' => 'araç tipi', 'plate' => 'plaka', 'driver_name' => 'şoför',
            'driver_phone' => 'şoför telefonu', 'reserved_seats' => 'ayrılan koltuklar', 'group_ids' => 'gruplar',
        ]);

        return $data;
    }
}
