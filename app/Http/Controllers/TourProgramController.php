<?php

namespace App\Http\Controllers;

use App\Actions\Tours\SaveProgramItem;
use App\Models\Tour;
use App\Models\TourProgramItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tur → "Program" sekmesi: gün gün etkinlikler (aile ekranı ve "Tur programı" çıktısı buradan okur).
 */
class TourProgramController extends Controller
{
    public function store(Request $request, Tour $tour, SaveProgramItem $save): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $save->handle($tour, $this->validated($request));

        return back();
    }

    public function update(Request $request, TourProgramItem $item, SaveProgramItem $save): RedirectResponse
    {
        Gate::authorize('update', $item->tour);

        $save->handle($item->tour, $this->validated($request), $item);

        return back();
    }

    public function destroy(TourProgramItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->tour);

        $item->delete();

        return back();
    }

    /**
     * @return array{day: string, time?: string|null, title: string, place?: string|null}
     */
    private function validated(Request $request): array
    {
        /** @var array{day: string, time?: string|null, title: string, place?: string|null} */
        return $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:150'],
            'place' => ['nullable', 'string', 'max:100'],
        ], [], ['day' => 'gün', 'time' => 'saat', 'title' => 'etkinlik', 'place' => 'yer']);
    }
}
