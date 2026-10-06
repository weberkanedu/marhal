<?php

namespace App\Http\Controllers;

use App\Models\BadgeSetting;
use App\Models\Group;
use App\Models\Tour;
use App\Reports\Definitions\TourBadges;
use App\Support\GroupColors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tur → "Yaka kartları" ekranı (tasarımdaki gibi): solda boy, önizlenen yolcu, alanlar, arka yüz,
 * grup renkleri ve basılacaklar; sağda kartın ön / arka yüzü ve A4 dizilimi. Ayarlar acente geneli
 * (badge_settings) saklanır; PDF ReportController@tourBadges ile aynı veriden üretilir.
 */
class TourBadgeController extends Controller
{
    public function show(Request $request, Tour $tour, TourBadges $definition): Response
    {
        Gate::authorize('update', $tour);

        $settings = BadgeSetting::current();
        $tenant = $request->user()?->tenant;
        $groups = $tour->groups()->orderBy('name')->get();
        $colors = GroupColors::forGroups($groups);

        return Inertia::render('tours/Badges', [
            'tour' => [
                'id' => $tour->id,
                'name' => $tour->name,
                'dates' => $tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y'),
            ],
            'agency' => [
                'name' => $tenant?->name,
                'phone' => $tenant?->phone,
                'logo_url' => $tenant?->logo_path ? route('agency.logo') : null,
            ],
            'settings' => [
                'size' => $settings->size->value,
                'fields' => $settings->fields,
                'back_languages' => $settings->back_languages,
                'back_side' => $settings->back_side,
                'health_note' => $settings->health_note,
            ],
            // Önizleme için bütün alanlar açık hesaplanır; ekranda ayara göre gizlenir.
            'badges' => $definition->build(
                $tour,
                settings: new BadgeSetting(['fields' => BadgeSetting::FIELDS, 'health_note' => true]),
                forScreen: true,
            ),
            'groups' => $groups->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name, 'color' => $colors[$g->id]])->values(),
            'palette' => GroupColors::PALETTE,
        ]);
    }

    /**
     * Grup rengi (kart bandı ve otobüs tabelası).
     */
    public function color(Request $request, Group $group): RedirectResponse
    {
        Gate::authorize('update', $group->tour);

        $group->update($request->validate(['color' => ['required', Rule::in(GroupColors::PALETTE)]]));

        return back();
    }
}
