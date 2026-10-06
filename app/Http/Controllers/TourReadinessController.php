<?php

namespace App\Http\Controllers;

use App\Actions\Readiness\MarkReadiness;
use App\Actions\Readiness\SetRavzaAppointments;
use App\Actions\Readiness\SetTourReadinessItems;
use App\Enums\ReadinessStatus;
use App\Enums\UserRole;
use App\Models\ReadinessItem;
use App\Models\Registration;
use App\Models\Tour;
use App\Models\User;
use App\Support\Readiness\ReadinessBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Tur → "Hazırlık" sekmesi: madde seçimi (personel), hücre işaretleme ve sütunu toplu işaretleme
 * (personel; rehber yalnız kendi grubunun yolcuları için), Ravza randevuları (personel).
 * Tablo verisi TourController@show içinde ertelenmiş "readiness" olarak gelir (ReadinessBoard).
 */
class TourReadinessController extends Controller
{
    public function items(Request $request, Tour $tour, SetTourReadinessItems $set): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $data = $request->validate(['item_ids' => ['array'], 'item_ids.*' => ['uuid']]);
        $set->handle($tour, $data['item_ids'] ?? []);

        return back();
    }

    public function mark(Request $request, Tour $tour, MarkReadiness $mark): RedirectResponse
    {
        $groups = $this->authorizeMarking($request->user(), $tour);

        $data = $request->validate([
            'registration_id' => ['required', 'uuid'],
            'item_id' => ['required', 'uuid'],
            'status' => ['nullable', Rule::enum(ReadinessStatus::class)],
        ]);

        // Acente kapsamı (global scope) sayesinde başka acentenin kaydı / maddesi bulunamaz → 404.
        $registration = Registration::query()
            ->whereKey($data['registration_id'])
            ->where('tour_id', $tour->id)
            ->when($groups !== null, fn ($q) => $q->whereIn('group_id', $groups ?? []))
            ->firstOrFail();
        $item = ReadinessItem::query()->whereKey($data['item_id'])->firstOrFail();

        $mark->handle($tour, $registration, $item, isset($data['status']) ? ReadinessStatus::from($data['status']) : null, $request->user());

        return back();
    }

    /**
     * Sütunu toplu "tamam": bütün tur ya da bir grup (rehber yalnız kendi grupları).
     */
    public function column(Request $request, Tour $tour, MarkReadiness $mark, ReadinessBoard $board): RedirectResponse
    {
        $groups = $this->authorizeMarking($request->user(), $tour);

        $data = $request->validate(['item_id' => ['required', 'uuid'], 'group_id' => ['nullable', 'uuid']]);
        $item = ReadinessItem::query()->whereKey($data['item_id'])->firstOrFail();

        $scope = $groups;
        if (isset($data['group_id'])) {
            abort_if($groups !== null && ! in_array($data['group_id'], $groups, true), 403);
            $scope = [$data['group_id']];
        }

        $count = $mark->column($tour, $item, $board->registrations($tour, $scope), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$item->name}: {$count} yolcu işaretlendi."]);

        return back();
    }

    public function ravza(Request $request, Tour $tour, SetRavzaAppointments $set): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $data = $request->validate([
            'men' => ['nullable', 'date'],
            'women' => ['nullable', 'date'],
        ], [], ['men' => 'erkekler randevusu', 'women' => 'kadınlar randevusu']);

        $set->handle($tour, $data['men'] ?? null, $data['women'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ravza randevuları kaydedildi.']);

        return back();
    }

    /**
     * Personel bütün turu, rehber yalnız kendi gruplarını işaretler. Dönen: rehberin grupları ya da null.
     *
     * @return list<string>|null
     */
    private function authorizeMarking(?User $user, Tour $tour): ?array
    {
        Gate::authorize('view', $tour);

        if (! $user?->hasRole(UserRole::Guide)) {
            Gate::authorize('update', $tour);

            return null;
        }

        /** @var list<string> $groups */
        $groups = $tour->groups()->where('guide_user_id', $user->getKey())->pluck('id')->all();
        abort_if($groups === [], 403);

        return $groups;
    }
}
