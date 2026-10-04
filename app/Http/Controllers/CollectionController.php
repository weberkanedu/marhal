<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Support\Collections\CollectionFilters;
use App\Support\Collections\CollectionQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tahsilat ekranı: borçlu yolcular, ödemesi tamamlananlar ve tarih aralığındaki tahsilatlar.
 * Excel/PDF çıktısı ReportController@collections ile aynı sorgudan üretilir.
 */
class CollectionController extends Controller
{
    public function __invoke(Request $request, CollectionQuery $query): Response
    {
        Gate::authorize('viewAny', Tour::class);

        $filters = CollectionFilters::fromRequest($request);

        return Inertia::render('collections/Index', [
            'tab' => $filters->tab,
            'filters' => $filters->toArray(),
            'tours' => Tour::query()->orderByDesc('start_date')->get(['id', 'name']),
            'rows' => $query->get($filters),
        ]);
    }
}
