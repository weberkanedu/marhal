<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionState;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Platform yöneticisi "Ödeme geldi" der (havale / EFT; iyzico 10e'de aynı sınıfı kullanacak).
 *
 * Kurallar: ödeme bir dönem açar (aylık 1 ay, yıllık 12 ay). Aktif ya da gecikmedeki acentede yeni dönem
 * eski dönemin bittiği yerden devam eder (ek süre bedava değildir); denemede ya da salt okunurdan dönüşte
 * bugünden başlar. Acente aktif olur, paket ve dönem ödemedekine geçer, bekleyen talep kapanır.
 * Abonelik başlangıcı (kota yılı) ilk ödemede ya da salt okunurdan dönüşte yazılır.
 */
class RecordSubscriptionPayment
{
    /**
     * @param  array{plan_id: string, billing_cycle: string, amount: int|float|string, method: string, paid_at: string, note?: string|null}  $data
     */
    public function handle(Tenant $tenant, array $data, ?User $actor = null): SubscriptionPayment
    {
        $state = $tenant->subscriptionState();

        if ($state === SubscriptionState::Suspended) {
            throw ValidationException::withMessages(['plan_id' => 'Askıdaki acenteye ödeme girilemez; önce durumunu değiştirin.']);
        }

        $plan = Plan::query()->whereKey($data['plan_id'])->firstOrFail();
        $cycle = BillingCycle::from($data['billing_cycle']);
        $now = CarbonImmutable::now();

        $continues = in_array($state, [SubscriptionState::Active, SubscriptionState::PastDue], true)
            && $tenant->subscription_ends_at !== null;
        $start = $continues ? $tenant->subscription_ends_at : $now;
        $end = $start->addMonthsNoOverflow($cycle->months());

        return DB::transaction(function () use ($tenant, $data, $actor, $plan, $cycle, $start, $end, $continues): SubscriptionPayment {
            $payment = new SubscriptionPayment([
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'amount' => $data['amount'],
                'currency' => $plan->currency,
                'method' => SubscriptionPaymentMethod::from($data['method']),
                'paid_at' => $data['paid_at'],
                'period_starts_at' => $start,
                'period_ends_at' => $end,
                'note' => $data['note'] ?? null,
                'recorded_by' => $actor?->id,
            ]);
            $payment->tenant_id = $tenant->id;
            $payment->save();

            $tenant->update([
                'plan_id' => $plan->id,
                'status' => TenantStatus::Active,
                'billing_cycle' => $cycle,
                'subscription_ends_at' => $end,
                'subscription_started_at' => $continues ? ($tenant->subscription_started_at ?? $start) : $start,
                'requested_plan_id' => null,
                'requested_billing_cycle' => null,
                'requested_at' => null,
            ]);

            return $payment;
        });
    }
}
