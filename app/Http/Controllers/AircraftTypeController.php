<?php

namespace App\Http\Controllers;

use App\Models\AircraftType;
use App\Support\Flights\AircraftLayout;
use App\Support\Flights\AircraftPresets;
use App\Support\TurkishText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente ayarları → Uçak tipleri. Hazır tipler tek tıkla eklenir; düzen (sıra aralığı, acil çıkışlar)
 * acentenin havayoluna göre değiştirilebilir. Değişiklik mevcut uçuşların planını etkilemez.
 */
class AircraftTypeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AircraftType::class);

        $types = AircraftType::query()->withCount('flights')->get()
            ->sort(fn (AircraftType $a, AircraftType $b) => TurkishText::compare($a->name, $b->name))
            ->values();

        return Inertia::render('aircraft-types/Index', [
            'types' => $types->map(fn (AircraftType $t) => [
                ...$t->only(['id', 'name', 'cabin', 'first_row', 'last_row', 'notes']),
                'exit_rows' => array_map('intval', $t->exit_rows ?? []),
                'label' => $t->layout()->label(),
                'flights_count' => $t->flights_count,
            ]),
            'presets' => collect(AircraftPresets::all())
                ->reject(fn (array $p) => $types->contains('name', $p['name']))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AircraftType::class);

        $type = $request->filled('preset')
            ? self::fromPreset((string) $request->validate(['preset' => ['required', Rule::in(collect(AircraftPresets::all())->pluck('key'))]])['preset'])
            : AircraftType::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$type->name} eklendi ({$type->layout()->label()})."]);

        return back();
    }

    public function update(Request $request, AircraftType $aircraftType): RedirectResponse
    {
        Gate::authorize('update', $aircraftType);

        $aircraftType->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Uçak tipi kaydedildi. Mevcut uçuşların planı değişmez.']);

        return back();
    }

    public function destroy(AircraftType $aircraftType): RedirectResponse
    {
        Gate::authorize('delete', $aircraftType);

        $aircraftType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$aircraftType->name} silindi."]);

        return back();
    }

    /**
     * Hazır tipi acentenin listesine ekler (aynı adla varsa onu kullanır).
     */
    public static function fromPreset(string $key): AircraftType
    {
        $preset = AircraftPresets::find($key) ?? abort(404);

        return AircraftType::query()->firstOrCreate(
            ['name' => $preset['name']],
            ['cabin' => $preset['cabin'], 'first_row' => $preset['first_row'], 'last_row' => $preset['last_row'], 'exit_rows' => $preset['exit_rows']],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        // "11, 12 25" gibi yazımlar kabul edilir.
        $request->merge([
            'exit_rows' => collect(preg_split('/[^\d]+/', (string) $request->input('exit_rows', '')) ?: [])
                ->filter(fn ($v) => $v !== '')->map(fn ($v) => (int) $v)->unique()->sort()->values()->all(),
            'cabin' => preg_replace('/\s+/', '', (string) $request->input('cabin')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'cabin' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! AircraftLayout::validCabin((string) $value)) {
                    $fail('Kabin düzeni 3-3, 2-4-2, 3-4-3 gibi yazılmalı.');
                }
            }],
            'first_row' => ['required', 'integer', 'min:1', 'max:99'],
            'last_row' => ['required', 'integer', 'gte:first_row', 'max:99'],
            'exit_rows' => ['array', 'max:6'],
            'exit_rows.*' => ['integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'name' => 'ad', 'cabin' => 'kabin düzeni', 'first_row' => 'ilk sıra', 'last_row' => 'son sıra', 'exit_rows' => 'acil çıkış sıraları',
        ]);

        $data['exit_rows'] = array_values(array_filter($data['exit_rows'] ?? [], fn (int $r) => $r >= $data['first_row'] && $r <= $data['last_row']));

        return $data;
    }
}
