<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\RequestPlan;
use App\Enums\BillingCycle;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\Subscriptions\SubscriptionSummary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente ayarları → "Paketim" (yalnız acente yöneticisi): paket kartları, durum, kullanım, havale bilgileri,
 * ödeme geçmişi. Paket seçimi talep olarak platforma düşer (RequestPlan).
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function show(SubscriptionSummary $summary): Response
    {
        $tenant = $this->currentTenant->get();
        abort_if($tenant === null, 403);
        $tenant->load(['plan', 'requestedPlan']);

        $plans = Plan::query()
            ->where('is_public', true)
            ->orWhere('id', $tenant->plan_id)
            ->with(['features' => fn ($q) => $q->where('enabled', true)])
            ->orderBy('sort')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                ...$plan->only(['id', 'name', 'tagline', 'price_monthly', 'price_yearly', 'user_limit', 'passenger_limit', 'is_featured', 'is_public']),
                'features' => $plan->features->map(fn (PlanFeature $f) => $f->feature_key)->values(),
            ]);

        return Inertia::render('agency/Plan', [
            'plans' => $plans,
            'featureOptions' => Feature::options(),
            'current' => $summary->detail($tenant),
            'bank' => [
                'name' => config('marhal.billing.bank'),
                'iban' => config('marhal.billing.iban'),
                'holder' => config('marhal.billing.holder'),
                'reference' => $tenant->slug,
            ],
            'cycles' => BillingCycle::options(),
        ]);
    }

    public function request(Request $request, RequestPlan $requestPlan): RedirectResponse
    {
        $tenant = $this->currentTenant->get();
        abort_if($tenant === null, 403);

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
        ]);

        $plan = Plan::query()->whereKey($data['plan_id'])->firstOrFail();
        $requestPlan->handle($tenant, $plan, BillingCycle::from($data['billing_cycle']));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$plan->name} talebiniz alındı. Ödeme ulaşınca paketiniz açılır."]);

        return back();
    }

    public function cancel(RequestPlan $requestPlan): RedirectResponse
    {
        $tenant = $this->currentTenant->get();
        abort_if($tenant === null, 403);

        $requestPlan->cancel($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paket talebi geri alındı.']);

        return back();
    }
}
