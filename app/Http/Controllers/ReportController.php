<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Tour;
use App\Reports\Definitions\CollectionReport;
use App\Reports\Definitions\TourPassengerList;
use App\Reports\Definitions\TourPaymentStatus;
use App\Reports\ReportResponder;
use App\Support\Collections\CollectionFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel / PDF indirmeleri. ?format=xlsx|pdf (varsayılan xlsx).
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportResponder $responder) {}

    public function tourPassengers(Request $request, Tour $tour, TourPassengerList $definition): Response
    {
        Gate::authorize('view', $tour);

        $user = $request->user();
        $groups = Group::query()
            ->where('tour_id', $tour->getKey())
            // Rehber sadece kendi grubunun listesini alabilir.
            ->when($user?->hasRole(UserRole::Guide), fn ($q) => $q->where('guide_user_id', $user?->getKey()));

        $groupId = $request->query('group');
        $group = match (true) {
            is_string($groupId) && $groupId !== '' => $groups->whereKey($groupId)->firstOrFail(),
            $user?->hasRole(UserRole::Guide) ?? false => $groups->firstOrFail(),
            default => null,
        };

        // Tam kimlik / pasaport no sadece maskesiz görme yetkisi olan kullanıcıya.
        $revealIds = $request->user()?->role->canRevealSensitiveData() ?? false;

        return $this->responder->download($definition->build($tour, $group, $revealIds), $this->format($request));
    }

    public function tourPayments(Request $request, Tour $tour, TourPaymentStatus $definition): Response
    {
        Gate::authorize('viewFinance', $tour);

        return $this->responder->download($definition->build($tour), $this->format($request));
    }

    public function collections(Request $request, CollectionReport $definition): Response
    {
        Gate::authorize('viewAnyFinance', Tour::class);

        return $this->responder->download(
            $definition->build(CollectionFilters::fromRequest($request)),
            $this->format($request),
        );
    }

    private function format(Request $request): string
    {
        return $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
    }
}
