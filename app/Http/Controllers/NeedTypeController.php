<?php

namespace App\Http\Controllers;

use App\Enums\NeedCategory;
use App\Enums\NeedEffect;
use App\Models\NeedType;
use App\Models\PersonNeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente ayarları → İhtiyaç türleri. Türler silinmez, kapatılır (girilmiş profiller bozulmasın).
 * "Etki" yerleşim kurallarını belirler (hareket: ön bölge / asansöre yakın / acil çıkış yasağı).
 */
class NeedTypeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', NeedType::class);

        return Inertia::render('need-types/Index', [
            'types' => NeedType::query()->orderBy('sort')->orderBy('name')->get()->map(fn (NeedType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category->value,
                'category_label' => $t->category->label(),
                'effect' => $t->effect?->value,
                'effect_label' => $t->effect?->label(),
                'airline_code' => $t->airline_code,
                'is_active' => $t->is_active,
            ]),
            'profiles' => PersonNeed::query()->count(),
            'categories' => NeedCategory::options(),
            'effects' => NeedEffect::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', NeedType::class);

        $type = NeedType::create([...$this->validated($request), 'sort' => (int) NeedType::query()->max('sort') + 1]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$type->name} eklendi."]);

        return back();
    }

    public function update(Request $request, NeedType $needType): RedirectResponse
    {
        Gate::authorize('update', $needType);

        $needType->update($this->validated($request, $needType));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$needType->name} kaydedildi."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?NeedType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('need_types', 'name')->where('tenant_id', $request->user()?->tenant_id)->ignore($type?->id)],
            'category' => ['required', Rule::enum(NeedCategory::class)],
            'effect' => ['nullable', Rule::enum(NeedEffect::class)],
            'airline_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z]{3,5}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ], ['airline_code.regex' => 'Havayolu kodu 3–5 harf olmalı (örn. WCHR).'], [
            'name' => 'ad', 'category' => 'grup', 'effect' => 'etki', 'airline_code' => 'havayolu kodu',
        ]);

        $data['airline_code'] = isset($data['airline_code']) ? strtoupper($data['airline_code']) : null;

        return $data;
    }
}
