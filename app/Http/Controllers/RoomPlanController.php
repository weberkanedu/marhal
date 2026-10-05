<?php

namespace App\Http\Controllers;

use App\Actions\Rooms\AutoAssignRooms;
use App\Actions\Rooms\CopyRoomPlan;
use App\Actions\Rooms\DefineStayFloors;
use App\Actions\Rooms\StayOccupancy;
use App\Enums\NeedEffect;
use App\Enums\RegistrationStatus;
use App\Enums\RoomKind;
use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Models\PersonRelation;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use App\Models\User;
use App\Support\Needs\NeedProfiles;
use App\Support\TurkishText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bir konaklamanın (tur + otel) oda planı ekranı ve otomatik dağıtma.
 * Rehber: sadece kendi grubunun yolcularını görür, değiştiremez.
 */
class RoomPlanController extends Controller
{
    /** @var array<string, list<string>> person_id → ihtiyaç adları */
    private array $needLabels = [];

    /** @var array<string, true> hareket güçlüğü olan person_id'ler */
    private array $mobility = [];

    private bool $elevatorKnown = false;

    public function __construct(
        private readonly StayOccupancy $occupancy,
        private readonly NeedProfiles $needs,
    ) {}

    public function show(Request $request, TourHotel $stay): Response
    {
        $guideGroups = $this->authorizeView($request->user(), $stay);
        $canUpdate = $request->user()?->can('update', $stay->tour) ?? false;
        $stay->load(['hotel', 'tour:id,name,start_date,end_date', 'groups:id,name']);
        /** @var list<string> $stayGroupIds */
        $stayGroupIds = $stay->groups->pluck('id')->values()->all();

        $rooms = $stay->rooms()
            ->with(['assignments.registration.person', 'assignments.registration.group:id,name'])
            ->get()
            ->sort(fn (Room $a, Room $b) => strnatcmp((string) $a->floor, (string) $b->floor) ?: strnatcmp($a->room_no, $b->room_no))
            ->values();

        $expected = $this->occupancy->expected($stay);
        $assignedIds = $rooms->flatMap(fn (Room $room) => $room->assignments->pluck('registration_id'))->flip();
        $unassigned = $expected->reject(fn (Registration $r) => $assignedIds->has($r->id));

        if ($guideGroups !== null) {
            $rooms = $rooms->filter(fn (Room $room) => $room->assignments->contains(fn (RoomAssignment $a) => in_array($a->registration->group_id, $guideGroups, true)))->values();
            $unassigned = $unassigned->filter(fn (Registration $r) => in_array($r->group_id, $guideGroups, true));
        }

        // İhtiyaçlar (ad + hareket güçlüğü) ve "asansöre yakın" bilgisinin girilip girilmediği.
        $profiles = $this->needs->forPersons($rooms->flatMap(fn (Room $room) => $room->assignments->map(fn (RoomAssignment $a) => $a->registration->person_id))
            ->merge($unassigned->pluck('person_id')));
        $this->needLabels = NeedProfiles::labels($profiles);
        $this->mobility = array_map(fn () => true, array_filter($profiles, fn (array $items) => NeedProfiles::has($items, NeedEffect::Mobility)));
        $this->elevatorKnown = $rooms->contains(fn (Room $room) => $room->near_elevator);

        $roomNoByRegistration = $rooms->flatMap(fn (Room $room) => $room->assignments->mapWithKeys(fn (RoomAssignment $a) => [$a->registration_id => $room->room_no]));

        return Inertia::render('rooms/Plan', [
            'stay' => [
                'id' => $stay->id,
                'hotel_name' => $stay->hotel->name,
                'city_label' => $stay->hotel->city->label(),
                'check_in' => $stay->check_in->toDateString(),
                'check_out' => $stay->check_out->toDateString(),
                'nights' => $stay->nights(),
                'groups' => $stay->groups->pluck('name')->values(),
                'tour' => ['id' => $stay->tour->id, 'name' => $stay->tour->name],
                'floors_count' => $stay->hotel->floors_count,
                'used_floors' => array_map('intval', $stay->used_floors ?? []),
            ],
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'room_no' => $room->room_no,
                'floor' => $room->floor,
                'capacity' => $room->capacity,
                'kind' => $room->kind->value,
                'notes' => $room->notes,
                'near_elevator' => $room->near_elevator,
                'occupants' => $room->assignments
                    ->sortBy(fn (RoomAssignment $a) => $a->created_at)
                    ->values()
                    ->map(fn (RoomAssignment $a) => $guideGroups !== null && ! in_array($a->registration->group_id, $guideGroups, true)
                        ? ['assignment_id' => $a->id, 'registration_id' => null, 'full_name' => 'Başka grup', 'gender' => null, 'group_name' => null, 'room_type_label' => null, 'warnings' => []]
                        : [
                            'assignment_id' => $a->id,
                            ...$this->passenger($a->registration),
                            'warnings' => $this->warnings($a->registration, $room, $stayGroupIds),
                        ]),
            ]),
            'unassigned' => $this->withFamily($unassigned, $stay, $roomNoByRegistration),
            // İstisnalar: grubu bu otelde olmayan ama burada kalmak isteyen yolcular elle eklenebilir.
            'others' => $canUpdate ? $this->others($stay, $expected, $assignedIds) : [],
            'stats' => [
                'rooms' => $rooms->count(),
                'beds' => $rooms->sum('capacity'),
                'occupied' => $assignedIds->count(),
                'unassigned' => $unassigned->count(),
            ],
            'options' => [
                'kinds' => RoomKind::options(),
                'roomTypes' => RoomType::options(),
            ],
            // Oda düzeni kopyalanabilecek diğer oteller (aynı tur, en az bir yerleşimi olan).
            'copySources' => $canUpdate ? TourHotel::query()
                ->where('tour_id', $stay->tour_id)
                ->whereKeyNot($stay->getKey())
                ->whereHas('roomAssignments')
                ->with('hotel')
                ->withCount('roomAssignments')
                ->orderBy('check_in')
                ->get()
                ->map(fn (TourHotel $s) => [
                    'id' => $s->id,
                    'label' => "{$s->hotel->city->label()} — {$s->hotel->name} ({$s->room_assignments_count} kişi)",
                ])
                ->values() : [],
            'can' => [
                'update' => $canUpdate,
                'reports' => $canUpdate,
            ],
        ]);
    }

    /**
     * "Oteli tanımla": binanın kat sayısı ve bu konaklamada kullandığımız katlar.
     */
    public function floors(Request $request, TourHotel $stay, DefineStayFloors $define): RedirectResponse
    {
        Gate::authorize('update', $stay->tour);

        $data = $request->validate([
            'floors_count' => ['required', 'integer', 'min:1', 'max:150'],
            'used_floors' => ['array', 'max:150'],
            'used_floors.*' => ['integer', 'min:0', 'max:150'],
        ], [], ['floors_count' => 'kat sayısı', 'used_floors' => 'kullandığımız katlar']);

        $define->handle($stay, (int) $data['floors_count'], $data['used_floors'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Otel kat bilgisi kaydedildi.']);

        return back();
    }

    /**
     * Otomatik dağıtma önizlemesi (kaydetmez).
     */
    public function preview(TourHotel $stay, AutoAssignRooms $auto): JsonResponse
    {
        Gate::authorize('update', $stay->tour);

        $plan = $auto->plan($stay);

        return response()->json([
            'placements' => collect($plan['placements'])
                ->groupBy(fn (array $p) => $p['room']->id)
                ->map(fn (Collection $items) => [
                    'room_no' => $items->first()['room']->room_no,
                    'kind' => ($plan['kinds'][$items->first()['room']->id] ?? $items->first()['room']->kind)->label(),
                    'names' => $items->map(fn (array $p) => $p['registration']->person->full_name)->values(),
                ])
                ->sortBy('room_no', SORT_NATURAL)
                ->values(),
            'placed' => count($plan['placements']),
            'unplaced' => collect($plan['unplaced'])->map(fn (array $u) => [
                'name' => $u['registration']->person->full_name,
                'reason' => $u['reason'],
            ])->values(),
        ]);
    }

    public function apply(TourHotel $stay, AutoAssignRooms $auto): RedirectResponse
    {
        Gate::authorize('update', $stay->tour);

        $result = $auto->apply($stay);

        Inertia::flash('toast', [
            'type' => $result['unplaced'] > 0 ? 'warning' : 'success',
            'message' => "{$result['placed']} yolcu yerleştirildi.".($result['unplaced'] > 0 ? " {$result['unplaced']} yolcu için uygun yer bulunamadı." : ''),
        ]);

        return back();
    }

    /**
     * Başka bir otelin oda düzenini kopyalama önizlemesi (kaydetmez). ?from=<konaklama id>
     */
    public function copyPreview(Request $request, TourHotel $stay, CopyRoomPlan $copy): JsonResponse
    {
        Gate::authorize('update', $stay->tour);

        $plan = $copy->plan($this->copySource($request, $stay), $stay);

        return response()->json([
            'placed' => count($plan['placements']),
            'placements' => collect($plan['placements'])
                ->groupBy(fn (array $p) => $p['room']->id)
                ->map(fn (Collection $items) => [
                    'room_no' => $items->first()['room']->room_no,
                    'from' => $items->first()['from'],
                    'names' => $items->map(fn (array $p) => $p['registration']->person->full_name)->values(),
                ])
                ->sortBy('room_no', SORT_NATURAL)
                ->values(),
            'unplaced' => collect($plan['unplaced'])->map(fn (array $u) => [
                'name' => $u['registration']->person->full_name,
                'reason' => $u['reason'],
            ])->values(),
        ]);
    }

    public function copy(Request $request, TourHotel $stay, CopyRoomPlan $copy): RedirectResponse
    {
        Gate::authorize('update', $stay->tour);

        $result = $copy->apply($this->copySource($request, $stay), $stay);

        Inertia::flash('toast', [
            'type' => $result['unplaced'] > 0 ? 'warning' : 'success',
            'message' => "{$result['placed']} yolcu kopyalanan düzene göre yerleştirildi.".($result['unplaced'] > 0 ? " {$result['unplaced']} yolcu için boş oda yetmedi." : ''),
        ]);

        return back();
    }

    /**
     * Kopyalanacak konaklama aynı turdan, farklı bir konaklama olmalı (başka tur / acente → 404).
     */
    private function copySource(Request $request, TourHotel $stay): TourHotel
    {
        // Önce biçim kontrolü: geçersiz kimlik PostgreSQL'de sorgu hatası (500) verirdi.
        $from = $request->validate(['from' => ['required', 'uuid']], [], ['from' => 'kopyalanacak otel'])['from'];

        return TourHotel::query()
            ->where('tour_id', $stay->tour_id)
            ->whereKeyNot($stay->getKey())
            ->whereKey($from)
            ->firstOrFail();
    }

    /**
     * Rehberse görebileceği grup id'lerini, değilse null döner.
     *
     * @return list<string>|null
     */
    private function authorizeView(?User $user, TourHotel $stay): ?array
    {
        Gate::authorize('view', $stay->tour);

        if (! $user?->hasRole(UserRole::Guide)) {
            return null;
        }

        /** @var list<string> $groups */
        $groups = $stay->groups()->where('guide_user_id', $user->getKey())->pluck('groups.id')->values()->all();
        abort_if($groups === [], 404);

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function passenger(Registration $registration): array
    {
        return [
            'registration_id' => $registration->id,
            'full_name' => $registration->person->full_name,
            'gender' => $registration->person->gender->value,
            'group_name' => $registration->group?->name,
            'room_type' => $registration->room_type?->value,
            'room_type_label' => $registration->room_type?->label(),
            'needs' => $this->needLabels[$registration->person_id] ?? [],
        ];
    }

    /**
     * Engellemeyen uyarılar (müşteri kararı: oda tipi farkı sadece uyarı).
     *
     * @param  list<string>  $stayGroupIds
     * @return list<string>
     */
    private function warnings(Registration $registration, Room $room, array $stayGroupIds): array
    {
        $warnings = [];

        if ($registration->room_type !== null && $registration->room_type->capacity() !== $room->capacity) {
            $warnings[] = "Ödediği oda {$registration->room_type->label()}, yerleştiği oda {$room->capacity} kişilik";
        }

        if (! in_array($registration->group_id, $stayGroupIds, true)) {
            $warnings[] = 'Grubu bu otelde değil (istisna)';
        }

        // Asansöre yakın odalar işaretlenmişse: hareket güçlüğü olan yolcu uzak odada olmasın.
        if ($this->elevatorKnown && isset($this->mobility[$registration->person_id]) && ! $room->near_elevator) {
            $warnings[] = 'Hareket güçlüğü var; asansöre uzak oda';
        }

        return $warnings;
    }

    /**
     * Yerleşmemiş yolcular + turdaki yakınları (elle yerleştirirken aileyi birlikte tutmak için).
     *
     * @param  Collection<int, Registration>  $unassigned
     * @param  Collection<string, string>  $roomNoByRegistration
     * @return array<int, array<string, mixed>>
     */
    private function withFamily(Collection $unassigned, TourHotel $stay, Collection $roomNoByRegistration): array
    {
        $tourRegistrations = Registration::query()
            ->where('tour_id', $stay->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->with('person:id,first_name,last_name')
            ->get(['id', 'person_id'])
            ->keyBy('person_id');

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
                    ->map(function (PersonRelation $rel) use ($tourRegistrations, $roomNoByRegistration): array {
                        $related = $tourRegistrations[$rel->related_person_id];

                        return [
                            'name' => $related->person->full_name,
                            'relation' => $rel->relation->label(),
                            'room_no' => $roomNoByRegistration[$related->id] ?? null,
                        ];
                    })
                    ->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Grubu bu otelde olmayan, burada da odası olmayan aktif yolcular.
     *
     * @param  Collection<int, Registration>  $expected
     * @param  Collection<string, int>  $assignedIds
     * @return array<int, array<string, mixed>>
     */
    private function others(TourHotel $stay, Collection $expected, Collection $assignedIds): array
    {
        $overlapping = $this->occupancy->overlappingStayIds($stay);

        return Registration::query()
            ->where('tour_id', $stay->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereNotIn('id', $expected->pluck('id')->merge($assignedIds->keys()))
            ->with(['person', 'group:id,name', 'roomAssignments' => fn ($q) => $q->whereIn('tour_hotel_id', $overlapping)->with('room.stay.hotel:id,name')])
            ->get()
            ->sort(fn (Registration $a, Registration $b) => TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values()
            ->map(fn (Registration $r) => [
                ...$this->passenger($r),
                'elsewhere' => $r->roomAssignments->first()?->room->stay->hotel->name,
            ])
            ->values()
            ->all();
    }
}
