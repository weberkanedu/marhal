<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform yöneticisi: paket fiyatları, kullanıcı / aktif tur sınırları ve paketteki modüller.
 * Değişiklik o paketi kullanan bütün acentelere hemen yansır (FeatureGate önbelleği temizlenir).
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->with('features')
            ->withCount('tenants')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                ...$plan->only(['id', 'name', 'price_monthly', 'price_yearly', 'currency', 'user_limit', 'active_tour_limit']),
                'tenants_count' => $plan->tenants_count,
                'features' => $plan->features->where('enabled', true)->pluck('feature_key')->values(),
            ]);

        return Inertia::render('platform/Plans', [
            'plans' => $plans,
            'features' => collect(Feature::cases())->map(fn (Feature $f) => ['key' => $f->value, 'label' => TenantController::featureLabel($f)]),
            'currencies' => config('marhal.currencies'),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', Rule::in(config('marhal.currencies'))],
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'active_tour_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'features' => ['array'],
            'features.*' => [Rule::enum(Feature::class)],
        ], attributes: [
            'name' => 'paket adı', 'price_monthly' => 'aylık fiyat', 'price_yearly' => 'yıllık fiyat',
            'user_limit' => 'kullanıcı sınırı', 'active_tour_limit' => 'aktif tur sınırı',
        ]);

        DB::transaction(function () use ($plan, $data): void {
            $enabled = $data['features'] ?? [];
            unset($data['features']);
            $plan->update($data);
            foreach (Feature::cases() as $feature) {
                // Model olayları önbelleği temizler (PlanFeature::saved).
                PlanFeature::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $feature->value],
                    ['enabled' => in_array($feature->value, $enabled, true)],
                );
            }
        });

        $plan->flushTenantFeatureCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$plan->name} paketi kaydedildi."]);

        return back();
    }
}
