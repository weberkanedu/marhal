<?php

namespace App\Http\Controllers;

use App\Actions\Buses\AutoAssignSeats;
use App\Actions\Buses\BusPassengers;
use App\Actions\Rooms\StayOccupancy;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\PersonRelation;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Models\User;
use App\Support\TurkishText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Otobüsün koltuk planı ekranı ve otomatik dağıtma.
 * Rehber: sadece kendi grubunun yolcularını görür, değiştiremez.
 */
class SeatPlanController extends Controller
{
    public function __construct(
        private readonly BusPassengers $passengers,
        private readonly StayOccupancy $occupancy,
    ) {}

    public function show(Request $request, Bus $bus): Response
    {
        $guideGroups = $this->authorizeView($request->user(), $bus);
        $canUpdate = $request->user()?->can('update', $bus->tour) ?? false;
        $bus->load(['tour:id,name', 'groups:id,name', 'vehicleType:id,name']);
        $layout = $bus->layout();

        $seats = $bus->seats()->with(['registration.person', 'registration.group:id,name'])->get();
        $links = $this->occupancy->familyLinks($seats->map(fn (SeatAssignment $s) => $s->registration->person_id));
        $warnings = $this->passengers->warnings($layout, $seats, $links);

        $expected = $this->passengers->expected($bus);
        $seatedIds = $seats->pluck('registration_id')->flip();
        $unassigned = $expected->reject(fn (Registration $r) => $seatedIds->has($r->id));

        if ($guideGroups !== null) {
            $unassigned = $unassigned->filter(fn (Registration $r) => in_array($r->group_id, $guideGroups, true));
        }

        $hidden = fn (SeatAssignment $s) => $guideGroups !== null && ! in_array($s->registration->group_id, $guideGroups, true);
        $reservedCount = count($bus->reserved());

        return Inertia::render('buses/Seats', [
            'bus' => [
                'id' => $bus->id,
                'name' => $bus->name,
                'label' => $layout->label(),
                'vehicle_type' => $bus->vehicleType?->name,
                'plate' => $bus->plate,
                'driver_name' => $bus->driver_name,
                'driver_phone' => $bus->driver_phone,
                'reserved' => $bus->reserved(),
                'groups' => $bus->groups->pluck('name')->values(),
                'tour' => ['id' => $bus->tour->id, 'name' => $bus->tour->name],
            ],
            'grid' => $layout->grid(),
            'seats' => $seats->mapWithKeys(fn (SeatAssignment $s) => [$s->seat_no => $hidden($s)
                ? ['seat_id' => $s->id, 'registration_id' => null, 'full_name' => 'Başka grup', 'gender' => null, 'group_name' => null, 'warnings' => []]
                : [
                    'seat_id' => $s->id,
                    ...$this->passenger($s->registration),
                    'warnings' => $guideGroups !== null ? [] : ($warnings[$s->seat_no] ?? []),
                ]]),
            'unassigned' => $this->withFamily($unassigned, $bus, $seats),
            'others' => $canUpdate ? $this->others($bus, $expected, $seatedIds) : [],
            'stats' => [
                'seats' => $layout->seatCount() - $reservedCount,
                'reserved' => $reservedCount,
                'occupied' => $seats->count(),
                'unassigned' => $unassigned->count(),
            ],
            'can' => ['update' => $canUpdate, 'reports' => $canUpdate],
        ]);
    }

    public function preview(Bus $bus, AutoAssignSeats $auto): JsonResponse
    {
        Gate::authorize('update', $bus->tour);

        $plan = $auto->plan($bus);

        return response()->json([
            'placed' => count($plan['placements']),
            'placements' => collect($plan['placements'])
                ->sortBy('seat')
                ->map(fn (array $p) => ['seat' => $p['seat'], 'name' => $p['registration']->person->full_name])
                ->values(),
            'unplaced' => collect($plan['unplaced'])->map(fn (array $u) => [
                'name' => $u['registration']->person->full_name,
                'reason' => $u['reason'],
            ])->values(),
        ]);
    }

    public function apply(Bus $bus, AutoAssignSeats $auto): RedirectResponse
    {
        Gate::authorize('update', $bus->tour);

        $result = $auto->apply($bus);

        Inertia::flash('toast', [
            'type' => $result['unplaced'] > 0 ? 'warning' : 'success',
            'message' => "{$result['placed']} yolcu oturtuldu.".($result['unplaced'] > 0 ? " {$result['unplaced']} yolcuya koltuk kalmadı." : ''),
        ]);

        return back();
    }

    /**
     * Rehberse görebileceği grup id'lerini, değilse null döner.
     *
     * @return list<string>|null
     */
    private function authorizeView(?User $user, Bus $bus): ?array
    {
        Gate::authorize('view', $bus->tour);

        if (! $user?->hasRole(UserRole::Guide)) {
            return null;
        }

        /** @var list<string> $groups */
        $groups = $bus->groups()->where('guide_user_id', $user->getKey())->pluck('groups.id')->values()->all();
        abort_if($groups === [], 404);

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function passenger(Registration $registration): array
    {
        $person = $registration->person;

        return [
            'registration_id' => $registration->id,
            'full_name' => $person->full_name,
            'gender' => $person->gender->value,
            'age' => $person->birth_date?->age,
            'group_name' => $registration->group?->name,
        ];
    }

    /**
     * @param  Collection<int, Registration>  $unassigned
     * @param  Collection<int, SeatAssignment>  $seats
     * @return array<int, array<string, mixed>>
     */
    private function withFamily(Collection $unassigned, Bus $bus, Collection $seats): array
    {
        $tourRegistrations = Registration::query()
            ->where('tour_id', $bus->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->with('person:id,first_name,last_name')
            ->get(['id', 'person_id'])
            ->keyBy('person_id');
        $seatByRegistration = $seats->mapWithKeys(fn (SeatAssignment $s) => [$s->registration_id => $s->seat_no]);

        $relations = PersonRelation::query()
            ->whereIn('person_id', $unassigned->pluck('person_id'))
            ->whereIn('related_person_id', $tourRegistrations->keys())
            ->get()
            ->groupBy('person_id');

        return $unassigned
            ->sort(fn (Registration $a, Registration $b) => ($a->group->name ?? '') <=> ($b->group->name ?? '')
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values()
            ->map(fn (Registration $r) => [
                ...$this->passenger($r),
                'family' => ($relations[$r->person_id] ?? collect())
                    ->filter(fn (PersonRelation $rel) => $rel->relation->isFamily())
                    ->map(function (PersonRelation $rel) use ($tourRegistrations, $seatByRegistration): array {
                        $related = $tourRegistrations[$rel->related_person_id];

                        return [
                            'name' => $related->person->full_name,
                            'relation' => $rel->relation->label(),
                            'seat_no' => $seatByRegistration[$related->id] ?? null,
                        ];
                    })
                    ->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Grubu bu otobüste olmayan aktif yolcular (istisna olarak bu otobüse alınabilir).
     *
     * @param  Collection<int, Registration>  $expected
     * @param  Collection<string, int>  $seatedIds
     * @return array<int, array<string, mixed>>
     */
    private function others(Bus $bus, Collection $expected, Collection $seatedIds): array
    {
        return Registration::query()
            ->where('tour_id', $bus->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereNotIn('id', $expected->pluck('id')->merge($seatedIds->keys()))
            ->with(['person', 'group:id,name', 'seatAssignments.bus:id,name'])
            ->get()
            ->sort(fn (Registration $a, Registration $b) => TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values()
            ->map(function (Registration $r): array {
                $seat = $r->seatAssignments->first();

                return [
                    ...$this->passenger($r),
                    'elsewhere' => $seat ? "{$seat->bus->name}, koltuk {$seat->seat_no}" : null,
                ];
            })
            ->values()
            ->all();
    }
}
