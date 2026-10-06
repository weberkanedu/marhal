<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Plans\UpdatePlan;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform yöneticisi → Paketler: fiyat, personel ve yolcu kotası, satışta mı, paketteki modüller.
 * Kurallar UpdatePlan'da.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->with('features')
            ->withCount('tenants')
            ->orderBy('sort')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                ...$plan->only(['id', 'name', 'price_monthly', 'price_yearly', 'currency', 'user_limit', 'passenger_limit', 'is_public']),
                'tenants_count' => $plan->tenants_count,
                'features' => $plan->features->where('enabled', true)->pluck('feature_key')->values(),
            ]);

        return Inertia::render('platform/Plans', [
            'plans' => $plans,
            'featureOptions' => Feature::options(),
            'yearlyMonths' => Plan::YEARLY_MONTHS,
        ]);
    }

    public function update(Request $request, Plan $plan, UpdatePlan $update): RedirectResponse
    {
        /** @var array{price_monthly: int|float|string, user_limit: int|null, passenger_limit: int|null, is_public: bool, features?: list<string>} $data */
        $data = $request->validate([
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'passenger_limit' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'is_public' => ['required', 'boolean'],
            'features' => ['array'],
            'features.*' => [Rule::enum(Feature::class)],
        ], attributes: [
            'price_monthly' => 'aylık fiyat', 'user_limit' => 'personel sınırı', 'passenger_limit' => 'yolcu kotası',
        ]);

        $wasPublic = $plan->is_public;
        $update->handle($plan, [...$data, 'features' => $data['features'] ?? []]);

        $isPublic = (bool) $data['is_public'];
        $message = match (true) {
            $wasPublic && ! $isPublic => "{$plan->name} satıştan kaldırıldı · mevcut acenteler etkilenmez.",
            ! $wasPublic && $isPublic => "{$plan->name} satışa açıldı.",
            default => "{$plan->name} güncellendi.",
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
