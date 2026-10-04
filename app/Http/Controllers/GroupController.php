<?php

namespace App\Http\Controllers;

use App\Http\Requests\GroupRequest;
use App\Models\Group;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Tur içindeki gruplar. Ekranı turun detay sayfasıdır (tours/Show).
 */
class GroupController extends Controller
{
    public function store(GroupRequest $request, Tour $tour): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $tour->groups()->create($this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grup eklendi.']);

        return back();
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        Gate::authorize('update', $group->tour);

        $group->update($this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grup kaydedildi.']);

        return back();
    }

    /**
     * Grup silinince yolcuları turda kalır, sadece "grupsuz" olur.
     */
    public function destroy(Group $group): RedirectResponse
    {
        Gate::authorize('update', $group->tour);

        $group->registrations()->update(['group_id' => null]);
        $group->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$group->name} silindi; yolcuları grupsuz kaldı."]);

        return back();
    }

    /**
     * Sistemde kullanıcısı olan rehber seçildiyse adı ondan alınır.
     *
     * @return array<string, mixed>
     */
    private function data(GroupRequest $request): array
    {
        $data = $request->validated();

        if (! empty($data['guide_user_id'])) {
            $data['guide_name'] = User::whereKey($data['guide_user_id'])->value('name');
        }

        return $data;
    }
}
