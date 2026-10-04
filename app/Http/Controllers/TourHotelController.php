<?php

namespace App\Http\Controllers;

use App\Http\Requests\TourHotelRequest;
use App\Models\Tour;
use App\Models\TourHotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Turun konaklamaları (hangi grup hangi otelde, hangi tarihlerde). Ekranı tur detay sayfasıdır.
 */
class TourHotelController extends Controller
{
    public function store(TourHotelRequest $request, Tour $tour): RedirectResponse
    {
        Gate::authorize('update', $tour);

        DB::transaction(function () use ($request, $tour): void {
            $stay = $tour->stays()->create($request->safe()->except('group_ids'));
            $stay->groups()->sync($request->groupIds());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konaklama eklendi.']);

        return back();
    }

    public function update(TourHotelRequest $request, TourHotel $stay): RedirectResponse
    {
        Gate::authorize('update', $stay->tour);

        DB::transaction(function () use ($request, $stay): void {
            $stay->update($request->safe()->except('group_ids'));
            $stay->groups()->sync($request->groupIds());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konaklama kaydedildi.']);

        return back();
    }

    public function destroy(TourHotel $stay): RedirectResponse
    {
        Gate::authorize('update', $stay->tour);

        $stay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$stay->hotel->name} konaklaması kaldırıldı."]);

        return back();
    }
}
