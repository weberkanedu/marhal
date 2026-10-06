<?php

namespace App\Http\Controllers;

use App\Actions\Flights\FlightSeats;
use App\Actions\Rooms\StayOccupancy;
use App\Enums\UserRole;
use App\Models\AircraftType;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Support\FamilyUnits;
use App\Support\Flights\AircraftPresets;
use App\Support\TurkishText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uçuşun koltuk planı (havayoluna gönderilecek koltuk tercihleri): uçak tipi seçme, sürükle-bırak
 * yerleştirme, başka yolculara ait (gri) koltuklar. Rehber kendi grubunu görür, değiştiremez.
 */
class FlightSeatPlanController extends Controller
{
    public function __construct(
        private readonly FlightSeats $seats,
        private readonly StayOccupancy $occupancy,
    ) {}

    public function show(Request $request, Flight $flight): Response
    {
        Gate::authorize('view', $flight->tour);

        $user = $request->user();
        $canUpdate = $user?->can('update', $flight->tour) ?? false;
        $guideOf = $user?->hasRole(UserRole::Guide)
            ? $flight->tour->groups()->where('guide_user_id', $user->getKey())->pluck('id')->all()
            : null;
        $flight->load(['tour:id,name', 'aircraftType:id,name']);
        $layout = $flight->layout();

        $all = $flight->passengers()->with(['registration.person', 'registration.group:id,name'])->get();
        $warnings = $guideOf === null ? $this->seats->warnings($flight, $all) : [];
        $visible = $all
            ->filter(fn (FlightPassenger $p) => $guideOf === null || in_array($p->registration->group_id, $guideOf, true))
            ->sort(fn (FlightPassenger $a, FlightPassenger $b) => TurkishText::compare(
                $a->registration->person->last_name.' '.$a->registration->person->first_name,
                $b->registration->person->last_name.' '.$b->registration->person->first_name,
            ))
            ->values();

        return Inertia::render('flights/Seats', [
            'flight' => [
                ...FlightController::summary($flight),
                'title' => $flight->title(),
                'tour' => ['id' => $flight->tour->id, 'name' => $flight->tour->name],
                'aircraft_type_id' => $flight->aircraft_type_id,
                'aircraft' => $flight->aircraftType?->name,
            ],
            'cabin' => $layout ? [
                'groups' => $layout->groups(),
                'rows' => $layout->rows(),
                'exit_rows' => $layout->exitRows,
                'label' => $layout->label(),
                // Başka grubun / başka yolcuların koltukları rehbere ve personele gri görünür.
                'blocked' => [...$flight->blocked(), ...$all->diff($visible)->pluck('seat_no')->filter()->values()->all()],
            ] : null,
            'passengers' => $visible->map(fn (FlightPassenger $p) => [
                'id' => $p->id,
                'full_name' => $p->registration->person->full_name,
                'gender' => $p->registration->person->gender->value,
                'age' => $p->registration->person->birth_date?->diffInYears($flight->departure_at) !== null
                    ? (int) $p->registration->person->birth_date->diffInYears($flight->departure_at)
                    : null,
                'group_name' => $p->registration->group?->name,
                'seat_no' => $p->seat_no,
                'warnings' => $p->seat_no ? ($warnings[$p->seat_no] ?? []) : [],
            ]),
            'units' => $this->units($visible->filter(fn (FlightPassenger $p) => $p->seat_no === null)->values()),
            'aircraftTypes' => $canUpdate ? AircraftType::query()->get()
                ->sort(fn (AircraftType $a, AircraftType $b) => TurkishText::compare($a->name, $b->name))
                ->map(fn (AircraftType $t) => ['id' => $t->id, 'name' => $t->name, 'label' => $t->layout()->label()])
                ->values() : [],
            'presets' => $canUpdate ? collect(AircraftPresets::all())->map(fn (array $p) => ['key' => $p['key'], 'name' => $p['name'], 'cabin' => $p['cabin']])->values() : [],
            'can' => ['update' => $canUpdate],
        ]);
    }

    /**
     * Uçak tipini seç (acentenin tipi ya da hazır tiplerden biri; hazır tip acentenin listesine eklenir).
     */
    public function aircraft(Request $request, Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $data = $request->validate([
            'aircraft_type_id' => ['nullable', 'required_without:preset', 'uuid'],
            'preset' => ['nullable', 'required_without:aircraft_type_id', Rule::in(collect(AircraftPresets::all())->pluck('key'))],
        ]);

        $type = isset($data['aircraft_type_id'])
            ? AircraftType::query()->whereKey($data['aircraft_type_id'])->firstOrFail()
            : AircraftTypeController::fromPreset((string) $data['preset']);

        $this->seats->setAircraft($flight, $type);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Uçak: {$type->name} ({$type->layout()->label()})."]);

        return back();
    }

    public function assign(Request $request, Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $data = $request->validate([
            'passenger_id' => ['required', 'uuid'],
            'seat' => ['required', 'string', 'max:4'],
            'swap' => ['sometimes', 'boolean'],
        ]);

        $passenger = $flight->passengers()->whereKey($data['passenger_id'])->firstOrFail();
        $this->seats->assign($flight, $passenger, $data['seat'], (bool) ($data['swap'] ?? false));

        return back();
    }

    public function unassign(FlightPassenger $passenger): RedirectResponse
    {
        Gate::authorize('update', $passenger->flight->tour);

        $this->seats->unassign($passenger);

        return back();
    }

    public function auto(Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $result = $this->seats->autoAssign($flight);

        Inertia::flash('toast', [
            'type' => $result['unplaced'] ? 'warning' : 'success',
            'message' => "{$result['placed']} yolcu yerleştirildi.".($result['unplaced'] ? " {$result['unplaced']} yolcuya uygun koltuk kalmadı." : ''),
        ]);

        return back();
    }

    public function clear(Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $count = $this->seats->clear($flight);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Plan temizlendi: {$count} yolcunun koltuğu kaldırıldı."]);

        return back();
    }

    public function block(Request $request, Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $data = $request->validate(['seat' => ['required', 'string', 'max:4']]);
        $this->seats->toggleBlocked($flight, $data['seat']);

        return back();
    }

    /**
     * Koltuğu olmayanların aile kümeleri.
     *
     * @param  Collection<int, FlightPassenger>  $passengers
     * @return list<array{label: string|null, ids: list<string>}>
     */
    private function units(Collection $passengers): array
    {
        $byRegistration = $passengers->keyBy('registration_id');
        $links = $this->occupancy->familyLinks($passengers->map(fn (FlightPassenger $p) => $p->registration->person_id));
        $registrations = $passengers->map(fn (FlightPassenger $p) => $p->registration)->values()->all();

        return array_map(fn (array $unit) => [
            'label' => count($unit) > 1
                ? collect($unit)->map(fn ($r) => $r->person->last_name)->unique()->join(' / ').' ailesi'
                : null,
            'ids' => array_map(fn ($r) => $byRegistration[$r->id]->id, $unit),
        ], FamilyUnits::build(array_values($registrations), $links));
    }
}
