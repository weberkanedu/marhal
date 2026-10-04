<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Enums\Relation;
use App\Http\Requests\PersonRequest;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\Registration;
use App\Support\Audit\AuditLogger;
use App\Support\Media\PersonPhotoStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Person::class);

        $search = trim((string) $request->query('q', ''));

        $persons = Person::query()
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->orderByName()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Person $person) => $this->listItem($person));

        return Inertia::render('persons/Index', [
            'persons' => $persons,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Person::class);

        return Inertia::render('persons/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(PersonRequest $request, PersonPhotoStore $photos): RedirectResponse
    {
        Gate::authorize('create', Person::class);

        $person = new Person($request->personData());
        $person->kvkk_consent_at = $request->boolean('kvkk_consent') ? now() : null;
        $person->save();

        if ($request->hasFile('photo')) {
            $photos->store($person, $request->file('photo'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$person->full_name} kaydedildi."]);

        return to_route('persons.show', $person);
    }

    public function show(Request $request, Person $person): Response
    {
        Gate::authorize('view', $person);

        $registrations = $person->registrations()
            ->with(['tour:id,name,start_date,end_date', 'group:id,name'])
            ->withPaidTotal()
            ->latest('registered_at')
            ->get()
            ->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'tour' => $registration->tour->only(['id', 'name', 'start_date', 'end_date']),
                'group' => $registration->group?->name,
                'status' => $registration->status->value,
                'currency' => $registration->currency,
                'net_price' => $registration->netPrice(),
                'balance' => $registration->balance(),
            ]);

        return Inertia::render('persons/Show', [
            'person' => [
                ...$this->detail($person),
                'created_at' => $person->created_at?->toIso8601String(),
            ],
            'registrations' => $registrations,
            // Yakınlar: aile odası kuralı için (iki yönlü saklanır, burada bu kişiden bakılır).
            'relations' => $person->relations()
                ->with('relatedPerson:id,first_name,last_name,gender')
                ->get()
                ->filter(fn (PersonRelation $relation) => $relation->relatedPerson !== null)
                ->map(fn (PersonRelation $relation) => [
                    'id' => $relation->id,
                    'person_id' => $relation->related_person_id,
                    'full_name' => $relation->relatedPerson->full_name,
                    'relation' => $relation->relation->value,
                    'relation_label' => $relation->relation->label(),
                    'is_family' => $relation->relation->isFamily(),
                ])
                ->values(),
            'relationOptions' => Relation::options(),
            'can' => [
                'update' => $request->user()?->can('update', $person) ?? false,
                'delete' => $request->user()?->can('delete', $person) ?? false,
                'reveal' => $request->user()?->can('revealSensitive', $person) ?? false,
            ],
        ]);
    }

    public function edit(Person $person): Response
    {
        Gate::authorize('update', $person);

        return Inertia::render('persons/Edit', [
            'person' => $this->detail($person),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(PersonRequest $request, Person $person, PersonPhotoStore $photos): RedirectResponse
    {
        Gate::authorize('update', $person);

        $data = $request->personData();

        // Boş bırakılan kimlik/pasaport alanı mevcut değeri silmez (formda maskeli gösterilir).
        foreach (['national_id', 'passport_no'] as $field) {
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        $person->fill($data);

        if ($request->boolean('kvkk_consent') && $person->kvkk_consent_at === null) {
            $person->kvkk_consent_at = now();
        } elseif (! $request->boolean('kvkk_consent')) {
            $person->kvkk_consent_at = null;
        }

        $person->save();

        if ($request->hasFile('photo')) {
            $photos->store($person, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $photos->delete($person);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Değişiklikler kaydedildi.']);

        return to_route('persons.show', $person);
    }

    public function destroy(Person $person): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $hasActiveRegistration = $person->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereHas('tour', fn (Builder $query) => $query->whereDate('end_date', '>=', today()))
            ->exists();

        if ($hasActiveRegistration) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Kişinin devam eden bir tur kaydı var. Önce kaydı iptal edin.']);

            return back();
        }

        $person->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$person->full_name} silindi."]);

        return to_route('persons.index');
    }

    /**
     * Tura kayıt ekranındaki kişi arama kutusu için JSON sonuç.
     * `exclude_tour` verilirse o tura zaten kayıtlı kişiler işaretlenir.
     */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Person::class);

        $search = trim((string) $request->query('q', ''));
        $tourId = $request->query('exclude_tour');

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $persons = Person::query()
            ->tap(fn (Builder $query) => $this->applySearch($query, $search))
            ->when($tourId, fn (Builder $query) => $query->withExists([
                'registrations as already_registered' => fn (Builder $q) => $q->where('tour_id', $tourId),
            ]))
            ->orderByName()
            ->limit(10)
            ->get()
            ->map(fn (Person $person) => [
                ...$this->listItem($person),
                'already_registered' => (bool) ($person->getAttributes()['already_registered'] ?? false),
            ]);

        return response()->json($persons);
    }

    /**
     * Kimlik / pasaport numarasının tamamını gösterir (sadece yönetici) ve audit log'a yazar.
     */
    public function reveal(Person $person, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('revealSensitive', $person);

        $audit->log('view_sensitive', $person, ['fields' => ['national_id', 'passport_no']]);

        return response()->json([
            'national_id' => $person->national_id,
            'passport_no' => $person->passport_no,
        ]);
    }

    public function photo(Person $person, PersonPhotoStore $photos): StreamedResponse
    {
        Gate::authorize('view', $person);

        return $photos->response($person) ?? abort(404);
    }

    /**
     * Ad/soyad (her kelime ayrı), telefon; 11 haneli sayı ise T.C. Kimlik No,
     * harf+rakam ise pasaport no ile arar.
     *
     * @param  Builder<Person>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $compact = preg_replace('/\s+/', '', $search) ?? '';

        $query->where(function (Builder $query) use ($search, $compact): void {
            $query->where(function (Builder $query) use ($search): void {
                foreach (preg_split('/\s+/', $search) ?: [] as $term) {
                    $query->where(fn (Builder $q) => $q
                        ->whereLike('first_name', "%{$term}%")
                        ->orWhereLike('last_name', "%{$term}%"));
                }
            });

            if (preg_match('/^\d{3,}$/', $compact)) {
                $query->orWhereLike('phone', "%{$compact}%");
            }

            if (preg_match('/^\d{11}$/', $compact)) {
                $query->orWhere(fn (Builder $q) => $q->whereNationalId($compact));
            }

            if (preg_match('/^[A-Za-z]+\d+$/', $compact)) {
                $query->orWhere(fn (Builder $q) => $q->wherePassportNo($compact));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Person $person): array
    {
        return [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'gender' => $person->gender->value,
            'birth_date' => $person->birth_date?->toDateString(),
            'phone' => $person->phone,
            'masked_national_id' => $person->masked_national_id,
            'masked_passport_no' => $person->masked_passport_no,
            'passport_expiry_date' => $person->passport_expiry_date?->toDateString(),
            'passport_expiring' => $person->passport_expiry_date !== null && ! $person->passportValidFor(now()),
            'has_photo' => $person->photo_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Person $person): array
    {
        return [
            ...$person->only([
                'id', 'first_name', 'last_name', 'full_name', 'nationality', 'phone', 'email', 'address',
                'emergency_contact_name', 'emergency_contact_phone', 'notes',
                'masked_national_id', 'masked_passport_no',
            ]),
            'gender' => $person->gender->value,
            'birth_date' => $person->birth_date?->toDateString(),
            'passport_issue_date' => $person->passport_issue_date?->toDateString(),
            'passport_expiry_date' => $person->passport_expiry_date?->toDateString(),
            'passport_expiring' => $person->passport_expiry_date !== null && ! $person->passportValidFor(now()),
            'kvkk_consent' => $person->kvkk_consent_at !== null,
            'kvkk_consent_at' => $person->kvkk_consent_at?->toIso8601String(),
            'photo_url' => $person->photo_path ? route('persons.photo', $person).'?v='.$person->updated_at?->timestamp : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'genders' => Gender::options(),
        ];
    }
}
