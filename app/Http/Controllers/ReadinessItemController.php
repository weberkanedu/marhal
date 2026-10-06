<?php

namespace App\Http\Controllers;

use App\Enums\ReadinessKind;
use App\Models\ReadinessCheck;
use App\Models\ReadinessItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente ayarları → Hazırlık maddeleri. Maddeler silinmez, kapatılır (işaretlemeler bozulmasın).
 * "Yeni turlarda seçili": yeni bir turda kendiliğinden takip edilir; turda haplardan değiştirilir.
 */
class ReadinessItemController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ReadinessItem::class);

        return Inertia::render('readiness-items/Index', [
            'items' => ReadinessItem::query()->ordered()->get()->map(fn (ReadinessItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'kind' => $i->kind->value,
                'kind_label' => $i->kind->label(),
                'default_on' => $i->default_on,
                'is_active' => $i->is_active,
            ]),
            'checks' => ReadinessCheck::query()->count(),
            'kinds' => ReadinessKind::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ReadinessItem::class);

        $item = ReadinessItem::create([...$this->validated($request), 'sort' => (int) ReadinessItem::query()->max('sort') + 1]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$item->name} eklendi."]);

        return back();
    }

    public function update(Request $request, ReadinessItem $readinessItem): RedirectResponse
    {
        Gate::authorize('update', $readinessItem);

        $readinessItem->update($this->validated($request, $readinessItem));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$readinessItem->name} kaydedildi."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ReadinessItem $item = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('readiness_items', 'name')->where('tenant_id', $request->user()?->tenant_id)->ignore($item?->id)],
            'kind' => ['required', Rule::enum(ReadinessKind::class)],
            'default_on' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'ad', 'kind' => 'tür', 'default_on' => 'yeni turlarda seçili']);
    }
}
