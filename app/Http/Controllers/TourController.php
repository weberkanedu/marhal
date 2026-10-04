<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Enums\UserRole;
use App\Http\Requests\TourRequest;
use App\Models\Group;
use App\Models\Registration;
use App\Models\Tour;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy\CurrentTenant;
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

        $tours = Tour::query()
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
                ...$this->summary($tour),
                'groups_count' => $tour->groups_count,
                'registrations_count' => $tour->registrations_count,
            ]);

        $tenant = $currentTenant->get();

        return Inertia::render('tours/Index', [
            'tours' => $tours,
            'filter' => $filter,
            'limits' => [
                'active' => $tenant?->activeTourCount() ?? 0,
                'max' => $tenant?->plan->active_tour_limit,
            ],
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

    public function show(Request $request, Tour $tour): Response
    {
        Gate::authorize('view', $tour);

        $groups = $tour->groups()
            ->withCount(['registrations' => fn (Builder $q) => $q->where('status', '!=', RegistrationStatus::Cancelled)])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group) => [
                ...$group->only(['id', 'name', 'guide_user_id', 'guide_name', 'guide_phone', 'notes']),
                'guide_display' => $group->guide_name,
                'registrations_count' => $group->registrations_count,
            ]);

        $registrations = $tour->registrations()
            ->with(['person', 'group:id,name'])
            ->withPaidTotal()
            ->get()
            ->sortBy(fn (Registration $r) => [$r->status === RegistrationStatus::Cancelled ? 1 : 0, mb_strtolower($r->person->last_name.' '.$r->person->first_name)])
            ->values()
            ->map(fn (Registration $registration) => $this->registrationRow($registration, $tour));

        $active = $registrations->where('status', '!=', RegistrationStatus::Cancelled->value);

        return Inertia::render('tours/Show', [
            'tour' => [
                ...$this->summary($tour),
                'notes' => $tour->notes,
            ],
            'stats' => [
                'registered' => $active->count(),
                'confirmed' => $active->where('status', RegistrationStatus::Confirmed->value)->count(),
                'pending' => $active->where('status', RegistrationStatus::Pending->value)->count(),
                'cancelled' => $registrations->count() - $active->count(),
                'unassigned' => $active->whereNull('group_id')->count(),
                'total' => $active->reduce(fn (string $c, array $r) => Money::add($c, $r['net_price']), '0.00'),
                'paid' => $active->reduce(fn (string $c, array $r) => Money::add($c, $r['paid']), '0.00'),
                'balance' => $active->reduce(fn (string $c, array $r) => Money::add($c, $r['balance']), '0.00'),
            ],
            'groups' => $groups,
            'registrations' => $registrations,
            'options' => [
                ...$this->formOptions(),
                'roomTypes' => RoomType::options(),
                'registrationStatuses' => RegistrationStatus::options(),
                'guides' => User::query()
                    ->where('tenant_id', $tour->tenant_id)
                    ->where('role', UserRole::Guide)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'can' => [
                'update' => $request->user()?->can('update', $tour) ?? false,
                'delete' => $request->user()?->can('delete', $tour) ?? false,
            ],
        ]);
    }

    public function edit(Tour $tour): Response
    {
        Gate::authorize('update', $tour);

        return Inertia::render('tours/Edit', [
            'tour' => [...$this->summary($tour), 'notes' => $tour->notes],
            'options' => $this->formOptions(),
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
     * @return array<string, mixed>
     */
    private function summary(Tour $tour): array
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
            'default_price' => $tour->default_price,
            'currency' => $tour->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationRow(Registration $registration, Tour $tour): array
    {
        $person = $registration->person;

        return [
            'id' => $registration->id,
            'person' => [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'gender' => $person->gender->value,
                'phone' => $person->phone,
                'masked_passport_no' => $person->masked_passport_no,
                // Vize için pasaport tur başlangıcından itibaren en az 6 ay geçerli olmalı.
                'passport_expiring' => $person->passport_expiry_date !== null && ! $person->passportValidFor($tour->start_date),
                'passport_missing' => $person->passport_expiry_date === null,
            ],
            'group_id' => $registration->group_id,
            'group_name' => $registration->group?->name,
            'room_type' => $registration->room_type?->value,
            'status' => $registration->status->value,
            'price' => $registration->price,
            'discount' => $registration->discount,
            'net_price' => $registration->netPrice(),
            'paid' => $registration->paidTotal(),
            'balance' => $registration->balance(),
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
