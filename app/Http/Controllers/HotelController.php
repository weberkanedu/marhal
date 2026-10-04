<?php

namespace App\Http\Controllers;

use App\Enums\HotelCity;
use App\Http\Requests\HotelRequest;
use App\Models\Hotel;
use App\Support\TurkishText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acentenin otel listesi (Mekke / Medine / diğer). Turlara "Konaklama" olarak eklenir.
 */
class HotelController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Hotel::class);

        $hotels = Hotel::query()
            ->withCount('stays')
            ->get()
            ->sort(fn (Hotel $a, Hotel $b) => array_search($a->city, HotelCity::cases(), true) <=> array_search($b->city, HotelCity::cases(), true)
                ?: TurkishText::compare($a->name, $b->name))
            ->values()
            ->map(fn (Hotel $hotel) => [
                ...$hotel->only(['id', 'name', 'address', 'phone', 'stars', 'notes']),
                'city' => $hotel->city->value,
                'stays_count' => $hotel->stays_count,
            ]);

        return Inertia::render('hotels/Index', [
            'hotels' => $hotels,
            'cities' => HotelCity::options(),
        ]);
    }

    public function store(HotelRequest $request): RedirectResponse
    {
        Gate::authorize('create', Hotel::class);

        $hotel = Hotel::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$hotel->name} eklendi."]);

        return back();
    }

    public function update(HotelRequest $request, Hotel $hotel): RedirectResponse
    {
        Gate::authorize('update', $hotel);

        $hotel->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Otel bilgileri kaydedildi.']);

        return back();
    }

    /**
     * Bir turda kullanılan otel silinemez (konaklama ve oda planı ona bağlı).
     */
    public function destroy(Hotel $hotel): RedirectResponse
    {
        Gate::authorize('delete', $hotel);

        if ($hotel->stays()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$hotel->name} bir turun konaklamasında kullanılıyor; önce turdan kaldırın."]);

            return back();
        }

        $hotel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$hotel->name} silindi."]);

        return back();
    }
}
