<?php

namespace Database\Seeders\Demo;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Support\Needs\DefaultNeedTypes;
use App\Support\Readiness\DefaultReadinessItems;

/**
 * Paketler 10b örnek verisi: platform panelinde abonelik durumları görülsün diye kullanıcısız üç örnek acente
 * (deneme + paket talebi, gecikmede, salt okunur). Demo acente (Kafile, süresiz) olduğu gibi kalır.
 */
class SubscriptionDemo
{
    public function run(Tenant $tenant): string
    {
        $plans = Plan::query()->pluck('id', 'slug');

        if (! isset($plans['mikat'], $plans['kafile'])) {
            return 'Mikat / Kafile paketleri yok; abonelik örneği eklenmedi.';
        }

        $trial = $this->agency('Nur Yolu Seyahat (örnek)', 'nur-yolu-ornek', [
            'plan_id' => $plans['kafile'],
            'status' => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays(9),
            'requested_plan_id' => $plans['kafile'],
            'requested_billing_cycle' => BillingCycle::Yearly,
            'requested_at' => now()->subDay(),
        ]);

        $late = $this->agency('Harem Tur (örnek)', 'harem-tur-ornek', [
            'plan_id' => $plans['kafile'],
            'status' => TenantStatus::Active,
            'billing_cycle' => BillingCycle::Monthly,
            'subscription_started_at' => now()->subMonths(3)->subDays(2),
            'subscription_ends_at' => now()->subDays(2),
        ]);
        foreach ([3, 2, 1] as $monthsAgo) {
            $start = now()->subMonths($monthsAgo)->subDays(2);
            $this->payment($late, $plans['kafile'], BillingCycle::Monthly, '3490', $start, $start->copy()->addMonth());
        }

        $readOnly = $this->agency('Mina Seyahat (örnek)', 'mina-seyahat-ornek', [
            'plan_id' => $plans['mikat'],
            'status' => TenantStatus::Active,
            'billing_cycle' => BillingCycle::Yearly,
            'subscription_started_at' => now()->subYear()->subDays(10),
            'subscription_ends_at' => now()->subDays(10),
        ]);
        $this->payment($readOnly, $plans['mikat'], BillingCycle::Yearly, '14900', now()->subYear()->subDays(10), now()->subDays(10));

        return "Örnek acenteler: {$trial->name} ({$trial->subscriptionState()->label()}), "
            ."{$late->name} ({$late->subscriptionState()->label()}), {$readOnly->name} ({$readOnly->subscriptionState()->label()}).";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agency(string $name, string $slug, array $attributes): Tenant
    {
        $agency = Tenant::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'default_currency' => 'TRY', ...$attributes]);
        DefaultNeedTypes::seed($agency);
        DefaultReadinessItems::seed($agency);

        return $agency;
    }

    private function payment(Tenant $agency, string $planId, BillingCycle $cycle, string $amount, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $payment = new SubscriptionPayment([
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => 'TRY',
            'method' => SubscriptionPaymentMethod::Transfer,
            'paid_at' => $start,
            'period_starts_at' => $start,
            'period_ends_at' => $end,
        ]);
        $payment->tenant_id = $agency->id;
        $payment->save();
    }
}
