<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\Placements;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yan menüdeki "Ara" (Ctrl K): yolcu ve tur arar; ekranlar ve işlemler tarayıcıda süzülür.
 * Acente kapsamı (global scope) dışına çıkmaz; rehber yalnız kendi gruplarının turlarını bulur,
 * yolcu aramasını yalnız yolcuları görebilen personel kullanır.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        if ($user === null || mb_strlen($q) < 2 || ! ($tenant->get()?->hasFeature(Feature::Passengers) ?? false)) {
            return response()->json(['persons' => [], 'tours' => []]);
        }

        $persons = $user->can('viewAny', Person::class)
            ? Person::query()
                ->search($q)
                ->with(['registrations' => fn ($r) => $r
                    ->where('status', '!=', RegistrationStatus::Cancelled)
                    ->with(['tour:id,name,start_date', ...Placements::ROOM_RELATIONS, ...Placements::SEAT_RELATIONS])
                    ->latest('registered_at'),
                ])
                ->orderByName()
                ->limit(6)
                ->get()
                ->map(fn (Person $p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    // Tasarımdaki gibi: "Koltuk 7 · Mekke oda 1201", yoksa turun adı.
                    'sub' => ($r = $p->registrations->first()) instanceof Registration
                        ? (Placements::text(Placements::all($r)) ?? $r->tour->name)
                        : 'Tura kayıtlı değil',
                ])
                ->values()
            : collect();

        $tours = $user->can('viewAny', Tour::class)
            ? Tour::query()
                ->whereLike('name', "%{$q}%")
                ->when($user->hasRole(UserRole::Guide), fn (Builder $t) => $t->whereHas('groups', fn (Builder $g) => $g->where('guide_user_id', $user->getKey())))
                ->orderByDesc('start_date')
                ->limit(4)
                ->get(['id', 'name', 'start_date'])
                ->map(fn (Tour $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'sub' => $t->start_date->translatedFormat('j F Y'),
                ])
                ->values()
            : collect();

        return response()->json(['persons' => $persons, 'tours' => $tours]);
    }
}
