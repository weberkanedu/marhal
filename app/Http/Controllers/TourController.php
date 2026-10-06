<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\FlightDirection;
use App\Enums\HotelCity;
use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Enums\UserRole;
use App\Actions\Rooms\StayOccupancy;
use App\Http\Requests\TourRequest;
use App\Models\Bus;
use App\Models\FamilyLink;
use App\Models\Flight;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\Person;
use App\Models\ReadinessItem;
use App\Models\Registration;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\TourProgramItem;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\Dashboard\TourReadiness;
use App\Support\GroupColors;
use App\Support\Money;
use App\Support\Needs\NeedProfiles;
use App\Support\Placements;
use App\Support\Readiness\ReadinessBoard;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tours\TourJourney;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TourController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        Gate::authorize('viewAny', Tour::class);

        $filter = $request->query('filter', 'active');
        $user = $request->user();
        $isGuide = $user?->hasRole(UserRole::Guide) ?? false;

        $tours = Tour::query()
            // Rehber sadece rehberi olduğu grubun bulunduğu turları görür.
            ->when($isGuide, fn (Builder $query) => $query->whereHas('groups', fn (Builder $q) => $q->where('guide_user_id', $user?->getKey())))
            ->when($filter === 'active', fn (Builder $query) => $query->active())
            ->when($filter === 'past', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->whereNotIn('status', TourStatus::active())
                ->orWhereDate('end_date', '<', today())))
            ->withCount([
                'groups',
                'registrations as registrations_count' => fn (Builder $q) => $q->where('status', '!=', RegistrationStatus::Cancelled),
            ])
            ->orderBy('start_date', $filter === 'past' ? 'desc' : 'asc')
            ->get()
            ->map(fn (Tour $tour) => [
                ...$this->summary($tour, ! $isGuide),
                'groups_count' => $tour->groups_count,
                'registrations_count' => $tour->registrations_count,
            ]);

        $tenant = $currentTenant->get();

        return Inertia::render('tours/Index', [
            'tours' => $tours,
            'filter' => $filter,
            'limits' => $isGuide ? null : [
                'active' => $tenant?->activeTourCount() ?? 0,
                'max' => $tenant?->plan->active_tour_limit,
            ],
            'can' => ['create' => $user?->can('create', Tour::class) ?? false],
        ]);
    }

    public function create(CurrentTenant $currentTenant): Response
    {
        Gate::authorize('create', Tour::class);

        return Inertia::render('tours/Create', [
            'options' => $this->formOptions(),
            'defaults' => ['currency' => $currentTenant->get()->default_currency ?? 'USD'],
        ]);
    }

    public function store(TourRequest $request): RedirectResponse
    {
        Gate::authorize('create', Tour::class);

        $tour = Tour::create($request->validated());

        // Tek gruplu turlar için hazır bir grup; istenirse yeniden adlandırılır veya yenileri eklenir.
        $tour->groups()->create(['name' => 'A Grubu']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tour->name} oluşturuldu."]);

        return to_route('tours.show', $tour);
    }

    public function show(Request $request, Tour $tour, CurrentTenant $currentTenant, TourJourney $journey, TourReadiness $readiness, NeedProfiles $profiles, StayOccupancy $occupancy, ReadinessBoard $board): Response
    {
        Gate::authorize('view', $tour);

        $user = $request->user();
        $finance = $user?->can('viewFinance', $tour) ?? false;
        // Rehber modu: sadece kendi grupları, para bilgisi yok.
        $guideOf = $user?->hasRole(UserRole::Guide)
            ? $tour->groups()->where('guide_user_id', $user->getKey())->pluck('id')->all()
            : null;

        // Her parça yalnız istenince hesaplanır (kısmi yenilemede, ör. sadece "program", gerisi çalışmaz).
        $groups = fn () => $this->groupRows($tour, $guideOf);
        $rooms = $currentTenant->get()?->hasFeature(Feature::RoomPlanning) ?? false;
        $familyScreen = $guideOf === null && ($currentTenant->get()?->hasFeature(Feature::FamilyScreen) ?? false);
        $busPlanning = $currentTenant->get()?->hasFeature(Feature::BusPlanning) ?? false;

        $rowsMemo = null;
        $registrations = function () use (&$rowsMemo, $tour, $guideOf, $familyScreen, $rooms, $busPlanning, $profiles, $finance) {
            return $rowsMemo ??= $this->registrationRows($tour, $guideOf, $familyScreen, $rooms, $busPlanning, $profiles, $finance);
        };
        // Konaklama (oda planı modülü açıksa): rehber sadece kendi gruplarının otellerini görür.
        $stays = fn () => $rooms ? $tour->stays()
            ->when($guideOf !== null, fn (Builder $q) => $q->whereHas('groups', fn (Builder $g) => $g->whereIn('groups.id', $guideOf ?? [])))
            ->with(['hotel', 'groups:id,name'])
            ->withCount(['rooms', 'roomAssignments'])
            ->withSum('rooms', 'capacity')
            ->orderBy('check_in')
            ->get()
            ->map(fn (TourHotel $stay) => [
                'id' => $stay->id,
                'hotel_id' => $stay->hotel_id,
                'hotel_name' => $stay->hotel->name,
                'city' => $stay->hotel->city->value,
                'city_label' => $stay->hotel->city->label(),
                'check_in' => $stay->check_in->toDateString(),
                'check_out' => $stay->check_out->toDateString(),
                'nights' => $stay->nights(),
                'groups' => $stay->groups->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])->values(),
                'notes' => $stay->notes,
                'rooms_count' => $stay->rooms_count,
                'beds' => (int) $stay->rooms_sum_capacity,
                'occupied' => $stay->room_assignments_count,
                // Kart halkası: bu otelde kalması gereken yolcu sayısı; kat bilgisi kartın alt satırında.
                'expected' => $occupancy->expected($stay)->count(),
                'floors_count' => $stay->hotel->floors_count,
                'used_floors' => array_map('intval', $stay->used_floors ?? []),
            ])->values()->all() : null;

        // Uçuşlar (uçuş listesi modülü açıksa). Rehber uçuş bilgisini görür; yolcu sayısı kendi grubuyla sınırlı.
        $flights = fn () => ($currentTenant->get()?->hasFeature(Feature::FlightLists) ?? false) ? $tour->flights()
            ->withCount(['passengers' => fn (Builder $q) => $q->when($guideOf !== null, fn (Builder $p) => $p->whereHas('registration', fn (Builder $r) => $r->whereIn('group_id', $guideOf ?? [])))])
            ->withCount(['passengers as seated_count' => fn (Builder $q) => $q->whereNotNull('seat_no')])
            ->with('aircraftType:id,name')
            ->orderBy('departure_at')
            ->get()
            ->map(fn (Flight $flight) => [
                ...FlightController::summary($flight),
                'passengers_count' => $flight->passengers_count,
                'seated_count' => $flight->getAttribute('seated_count'),
                'aircraft' => $flight->aircraftType?->name,
            ]) : null;

        // Otobüsler (otobüs planı modülü açıksa): rehber sadece kendi gruplarının otobüslerini görür.
        $buses = fn () => $busPlanning ? $tour->buses()
            ->when($guideOf !== null, fn (Builder $q) => $q->whereHas('groups', fn (Builder $g) => $g->whereIn('groups.id', $guideOf ?? [])))
            ->with(['groups:id,name', 'vehicleType:id,name'])
            ->withCount('seats')
            ->orderBy('name')
            ->get()
            ->map(fn (Bus $bus) => [
                ...$bus->only(['id', 'name', 'vehicle_type_id', 'plate', 'driver_name', 'driver_phone', 'notes']),
                'vehicle_type' => $bus->vehicleType?->name,
                'label' => $bus->layout()->label(),
                'reserved' => $bus->reserved(),
                'groups' => $bus->groups->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])->values(),
                'seats' => $bus->layout()->seatCount() - count($bus->reserved()),
                'occupied' => $bus->seats_count,
            ])->values()->all() : null;

        return Inertia::render('tours/Show', [
            'tour' => [
                ...$this->summary($tour, $finance),
                'notes' => $tour->notes,
            ],
            'stats' => fn () => [
                'registered' => ($active = collect($registrations())->where('status', '!=', RegistrationStatus::Cancelled->value))->count(),
                'confirmed' => $active->where('status', RegistrationStatus::Confirmed->value)->count(),
                'pending' => $active->where('status', RegistrationStatus::Pending->value)->count(),
                'cancelled' => count($registrations()) - $active->count(),
                'unassigned' => $active->whereNull('group_id')->count(),
                'total' => $finance ? $active->reduce(fn (string $c, array $r) => Money::add($c, $r['net_price']), '0.00') : null,
                'paid' => $finance ? $active->reduce(fn (string $c, array $r) => Money::add($c, $r['paid']), '0.00') : null,
                'balance' => $finance ? $active->reduce(fn (string $c, array $r) => Money::add($c, $r['balance']), '0.00') : null,
            ],
            'journey' => fn () => $journey->for($tour, $currentTenant->get()),
            // Hazırlık halkaları (oda / koltuk / uçuş / tahsilat): ana paneldeki "Turların durumu" ile aynı hesap.
            // Rehber tur genelini değil kendi grubunu görür; halkalar personele.
            'readiness' => fn () => $guideOf === null ? $this->readiness($readiness->for($tour, $currentTenant->get()), $finance) : null,
            // Hazırlık sekmesi (modül açıksa): sekme açılınca yüklenir; rehber yalnız kendi grupları.
            // Gün gün program (aile ekranı ve "Tur programı" çıktısı).
            'program' => fn () => $tour->programItems()->get()->map(fn (TourProgramItem $i) => [
                'id' => $i->id,
                'day' => $i->day->toDateString(),
                'time' => $i->time ? substr($i->time, 0, 5) : null,
                'title' => $i->title,
                'place' => $i->place,
            ]),
            'readinessBoard' => ($currentTenant->get()?->hasFeature(Feature::Readiness) ?? false)
                ? Inertia::defer(fn () => [
                    ...$board->build($tour, $guideOf === null ? null : array_values(array_map('strval', $guideOf))),
                    'all_items' => $user?->can('update', $tour)
                        ? ReadinessItem::query()->where('is_active', true)->ordered()->get(['id', 'name'])
                        : [],
                ], 'readiness')
                : null,
            'groups' => $groups,
            'registrations' => $registrations,
            'stays' => $stays,
            'buses' => $buses,
            'flights' => $flights,
            'options' => fn () => [
                'vehicleTypes' => $busPlanning && ($user?->can('update', $tour) ?? false)
                    ? VehicleType::query()->orderBy('name')->get()
                        ->map(fn (VehicleType $t) => ['id' => $t->id, 'name' => $t->name, 'label' => $t->layout()->label()])
                    : [],
                'hotels' => $rooms && ($user?->can('update', $tour) ?? false)
                    ? Hotel::query()->get(['id', 'name', 'city'])
                        ->sortBy([fn (Hotel $a, Hotel $b) => array_search($a->city, HotelCity::cases(), true) <=> array_search($b->city, HotelCity::cases(), true)
                            ?: TurkishText::compare($a->name, $b->name)])
                        ->values()
                        ->map(fn (Hotel $h) => ['id' => $h->id, 'name' => $h->name, 'city' => $h->city->value, 'city_label' => $h->city->label()])
                    : [],
                ...$this->formOptions(),
                'roomTypes' => RoomType::options(),
                'flightDirections' => FlightDirection::options(),
                'registrationStatuses' => RegistrationStatus::options(),
                'guides' => User::query()
                    ->where('tenant_id', $tour->tenant_id)
                    ->where('role', UserRole::Guide)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'can' => [
                'update' => $user?->can('update', $tour) ?? false,
                'delete' => $user?->can('delete', $tour) ?? false,
                'viewFinance' => $finance,
                'viewPersons' => $user?->can('viewAny', Person::class) ?? false,
            ],
        ]);
    }

    public function edit(Tour $tour): Response
    {
        Gate::authorize('update', $tour);

        return Inertia::render('tours/Edit', [
            'tour' => [...$this->summary($tour), 'notes' => $tour->notes],
            'options' => $this->formOptions(),
            'canDelete' => auth()->user()?->can('delete', $tour) ?? false,
        ]);
    }

    public function update(TourRequest $request, Tour $tour): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $tour->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tur bilgileri kaydedildi.']);

        return to_route('tours.show', $tour);
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        Gate::authorize('delete', $tour);

        if ($tour->registrations()->where('status', '!=', RegistrationStatus::Cancelled)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Turda aktif kayıtlar var. Önce kayıtları iptal edin veya turu "İptal" durumuna alın.']);

            return back();
        }

        $tour->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tour->name} silindi."]);

        return to_route('tours.index');
    }

    /**
     * Turun grupları (rehberse yalnız kendi grupları), renk ve aktif yolcu sayısıyla.
     *
     * @param  array<int, mixed>|null  $guideOf
     * @return list<array<string, mixed>>
     */
    private function groupRows(Tour $tour, ?array $guideOf): array
    {
        $groups = $tour->groups()
            ->when($guideOf !== null, fn (Builder $q) => $q->whereIn('id', $guideOf ?? []))
            ->withCount(['registrations' => fn (Builder $q) => $q->where('status', '!=', RegistrationStatus::Cancelled)])
            ->orderBy('name')
            ->get();
        $colors = GroupColors::forGroups($groups);

        return array_values($groups->map(fn (Group $group) => [
            ...$group->only(['id', 'name', 'guide_user_id', 'guide_name', 'guide_phone', 'notes']),
            'color' => $colors[$group->id],
            'color_chosen' => $group->color !== null,
            'guide_display' => $group->guide_name,
            'registrations_count' => $group->registrations_count,
        ])->all());
    }

    /**
     * Tur yolcuları tablosunun satırları (oda / koltuk, ihtiyaç adları, aile linki).
     *
     * @param  array<int, mixed>|null  $guideOf
     * @return list<array<string, mixed>>
     */
    private function registrationRows(Tour $tour, ?array $guideOf, bool $familyScreen, bool $rooms, bool $busPlanning, NeedProfiles $profiles, bool $finance): array
    {
        $registrations = $tour->registrations()
            ->when($guideOf !== null, fn (Builder $q) => $q->whereIn('group_id', $guideOf ?? [])->where('status', '!=', RegistrationStatus::Cancelled))
            ->with(['person', 'group:id,name'])
            // Aile ekranı linki (personel kopyalar / iptal eder).
            ->when($familyScreen, fn (Builder $q) => $q->with(['familyLinks' => fn ($l) => $l->active()]))
            // Oda / koltuk bilgisi (modülü açıksa): listede ve rehber ekranında görünür.
            ->when($rooms, fn (Builder $q) => $q->with(Placements::ROOM_RELATIONS))
            ->when($busPlanning, fn (Builder $q) => $q->with(Placements::SEAT_RELATIONS))
            ->withPaidTotal()
            ->get()
            ->sort(fn (Registration $a, Registration $b) => ($a->status === RegistrationStatus::Cancelled) <=> ($b->status === RegistrationStatus::Cancelled)
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values();

        // İhtiyaç adları (rehber dahil; notlar yalnız yolcu sayfasında, personele).
        $needs = NeedProfiles::labels($profiles->forPersons($registrations->pluck('person_id')));

        return array_values($registrations->map(fn (Registration $registration) => [
            ...$this->registrationRow($registration, $tour, $finance),
            'needs' => $needs[$registration->person_id] ?? [],
            'family_link' => $familyScreen && ($link = $registration->familyLinks->first()) instanceof FamilyLink ? [
                'id' => $link->id,
                'url' => route('family.show', $link->token),
                'views' => $link->view_count,
                'expires_at' => $link->expires_at->toDateString(),
            ] : null,
        ])->all());
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    private function readiness(array $readiness, bool $finance): array
    {
        return $finance ? $readiness : [...$readiness, 'collection' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Tour $tour, bool $finance = true): array
    {
        return [
            'id' => $tour->id,
            'name' => $tour->name,
            'type' => $tour->type->value,
            'status' => $tour->status->value,
            'status_label' => $tour->status->label(),
            'start_date' => $tour->start_date->toDateString(),
            'end_date' => $tour->end_date->toDateString(),
            'capacity' => $tour->capacity,
            // Fiyat, para bilgisi yetkisi olmayan (rehber) kullanıcıya gönderilmez.
            'default_price' => $finance ? $tour->default_price : null,
            'currency' => $tour->currency,
            'whatsapp_link' => $tour->whatsapp_link,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationRow(Registration $registration, Tour $tour, bool $finance): array
    {
        $person = $registration->person;

        // Para bilgisi yetkisi olmayan (rehber) kullanıcıya ücret / bakiye gönderilmez.
        $money = $finance ? [
            'price' => $registration->price,
            'discount' => $registration->discount,
            'net_price' => $registration->netPrice(),
            'paid' => $registration->paidTotal(),
            'balance' => $registration->balance(),
        ] : ['price' => null, 'discount' => null, 'net_price' => null, 'paid' => null, 'balance' => null];

        return [
            'id' => $registration->id,
            'person' => [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'gender' => $person->gender->value,
                'age' => $person->birth_date ? (int) $person->birth_date->diffInYears($tour->start_date) : null,
                'phone' => $person->phone,
                'emergency_contact' => trim(($person->emergency_contact_name ?? '').' '.($person->emergency_contact_phone ?? '')) ?: null,
                'masked_passport_no' => $finance ? $person->masked_passport_no : null,
                // Vize için pasaport tur başlangıcından itibaren en az 6 ay geçerli olmalı.
                'passport_expiring' => $person->passport_expiry_date !== null && ! $person->passportValidFor($tour->start_date),
                'passport_missing' => $person->passport_expiry_date === null,
            ],
            'group_id' => $registration->group_id,
            'group_name' => $registration->group?->name,
            'room_type' => $registration->room_type?->value,
            'placements' => Placements::all($registration),
            // Tablodaki ayrı Oda ve Koltuk sütunları için.
            'rooms' => Placements::rooms($registration),
            'seat' => Placements::seat($registration),
            'status' => $registration->status->value,
            ...$money,
            'currency' => $registration->currency,
            'cancel_reason' => $registration->cancel_reason,
            'notes' => $registration->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => TourType::options(),
            'statuses' => TourStatus::options(),
            'currencies' => config('marhal.currencies'),
        ];
    }
}
