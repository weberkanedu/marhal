<?php

namespace App\Http\Controllers;

use App\Actions\Flights\FlightPassengers;
use App\Enums\FlightDirection;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Group;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\TurkishText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tur uçuşları (tur sayfasındaki "Uçuşlar" bölümü) ve uçuşun yolcu listesi sayfası.
 * Rehber: sadece kendi grubunun yolcularını, pasaport bilgisi olmadan görür.
 */
class FlightController extends Controller
{
    public function __construct(private readonly FlightPassengers $rules) {}

    public function show(Request $request, Flight $flight): Response
    {
        Gate::authorize('view', $flight->tour);

        $user = $request->user();
        $canUpdate = $user?->can('update', $flight->tour) ?? false;
        $guideOf = $user?->hasRole(UserRole::Guide)
            ? $flight->tour->groups()->where('guide_user_id', $user->getKey())->pluck('id')->all()
            : null;
        $flight->load('tour');

        $passengers = $flight->passengers()
            ->with(['registration.person', 'registration.group:id,name'])
            ->get()
            ->filter(fn (FlightPassenger $p) => $guideOf === null || in_array($p->registration->group_id, $guideOf, true))
            ->sort(fn (FlightPassenger $a, FlightPassenger $b) => TurkishText::compare(
                $a->registration->person->last_name.' '.$a->registration->person->first_name,
                $b->registration->person->last_name.' '.$b->registration->person->first_name,
            ))
            ->values()
            ->map(function (FlightPassenger $p) use ($flight, $guideOf): array {
                $person = $p->registration->person;

                return [
                    'id' => $p->id,
                    'registration_id' => $p->registration_id,
                    'title' => $this->rules->title($person, $flight->departure_at),
                    'full_name' => $person->full_name,
                    'group_name' => $p->registration->group?->name,
                    // Rehbere pasaport bilgisi gönderilmez.
                    'masked_passport_no' => $guideOf === null ? $person->masked_passport_no : null,
                    'passport_expiry' => $guideOf === null ? $person->passport_expiry_date?->toDateString() : null,
                    'pnr' => $p->pnr,
                    'ticket_no' => $p->ticket_no,
                    'warnings' => $guideOf === null ? $this->rules->warnings($person, $flight->tour) : [],
                ];
            });

        $onFlight = $flight->passengers()->pluck('registration_id')->flip();

        return Inertia::render('flights/Show', [
            'flight' => [
                ...$this->summary($flight),
                'tour' => ['id' => $flight->tour->id, 'name' => $flight->tour->name],
            ],
            'passengers' => $passengers,
            // Ekleme seçenekleri: gruplar (uçuşta olmayan yolcu sayısıyla) ve tek tek yolcular.
            'groups' => $canUpdate ? $flight->tour->groups()->orderBy('name')->get()
                ->map(fn (Group $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'missing' => $g->registrations()->where('status', '!=', RegistrationStatus::Cancelled)->whereNotIn('id', $onFlight->keys())->count(),
                ]) : [],
            'candidates' => $canUpdate ? Registration::query()
                ->where('tour_id', $flight->tour_id)
                ->where('status', '!=', RegistrationStatus::Cancelled)
                ->whereNotIn('id', $onFlight->keys())
                ->with(['person:id,first_name,last_name', 'group:id,name'])
                ->get()
                ->sort(fn (Registration $a, Registration $b) => TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
                ->values()
                ->map(fn (Registration $r) => ['id' => $r->id, 'full_name' => $r->person->full_name, 'group_name' => $r->group?->name]) : [],
            'can' => ['update' => $canUpdate, 'reports' => $canUpdate],
        ]);
    }

    public function store(Request $request, Tour $tour): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $flight = $tour->flights()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$flight->title()} eklendi. Yolcuları uçuş sayfasından ekleyin."]);

        return back();
    }

    public function update(Request $request, Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $flight->fill($this->validated($request));

        // Saat değişince yolculardan biri başka bir uçuşla çakışırsa kaydetme.
        $busy = array_intersect_key($this->rules->busy($flight), $flight->passengers()->pluck('registration_id')->flip()->all());
        if ($busy !== []) {
            throw ValidationException::withMessages([
                'departure_at' => count($busy).' yolcu bu saatlerde başka bir uçuşta ('.implode(', ', array_unique($busy)).').',
            ]);
        }

        $flight->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$flight->title()} kaydedildi."]);

        return back();
    }

    public function destroy(Flight $flight): RedirectResponse
    {
        Gate::authorize('update', $flight->tour);

        $flight->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$flight->title()} silindi."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Flight $flight): array
    {
        return [
            'id' => $flight->id,
            'direction' => $flight->direction->value,
            'direction_label' => $flight->direction->label(),
            'airline' => $flight->airline,
            'flight_no' => $flight->flight_no,
            'departure_airport' => $flight->departure_airport,
            'arrival_airport' => $flight->arrival_airport,
            'departure_at' => $flight->departure_at->format('Y-m-d\TH:i'),
            'arrival_at' => $flight->arrival_at->format('Y-m-d\TH:i'),
            'pnr' => $flight->pnr,
            'baggage' => $flight->baggage,
            'notes' => $flight->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'departure_airport' => strtoupper(trim((string) $request->input('departure_airport'))),
            'arrival_airport' => strtoupper(trim((string) $request->input('arrival_airport'))),
            'flight_no' => strtoupper(trim((string) $request->input('flight_no'))),
            'pnr' => $request->filled('pnr') ? strtoupper(trim((string) $request->input('pnr'))) : null,
        ]);

        return $request->validate([
            'direction' => ['required', Rule::enum(FlightDirection::class)],
            'airline' => ['required', 'string', 'max:100'],
            'flight_no' => ['required', 'string', 'max:20'],
            'departure_airport' => ['required', 'alpha', 'size:3'],
            'arrival_airport' => ['required', 'alpha', 'size:3', 'different:departure_airport'],
            'departure_at' => ['required', 'date'],
            'arrival_at' => ['required', 'date', 'after:departure_at'],
            'pnr' => ['nullable', 'string', 'max:20'],
            'baggage' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'departure_airport.size' => 'Havalimanı 3 harfli kodla yazılmalı (örn. IST).',
            'arrival_airport.size' => 'Havalimanı 3 harfli kodla yazılmalı (örn. JED).',
        ], [
            'direction' => 'yön', 'airline' => 'havayolu', 'flight_no' => 'uçuş no',
            'departure_airport' => 'kalkış havalimanı', 'arrival_airport' => 'varış havalimanı',
            'departure_at' => 'kalkış', 'arrival_at' => 'varış', 'pnr' => 'PNR', 'baggage' => 'bagaj',
        ]);
    }
}
