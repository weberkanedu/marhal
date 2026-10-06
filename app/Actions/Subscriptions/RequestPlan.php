<?php

namespace App\Actions\Subscriptions;

use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Acente yöneticisi "Paketim"den paket ve dönem seçer (kullanıcı kararı 2026-10-07): online ödeme gelene kadar
 * paket hemen değişmez; talep platform paneline düşer, ödeme gelince platform yöneticisi açar
 * (RecordSubscriptionPayment talebi kapatır). Askıda acente talep gönderemez (zaten giremez).
 */
class RequestPlan
{
    public function handle(Tenant $tenant, Plan $plan, BillingCycle $cycle): Tenant
    {
        if (! $plan->is_public) {
            throw ValidationException::withMessages(['plan_id' => 'Bu paket şu an satışta değil.']);
        }

        $tenant->update([
            'requested_plan_id' => $plan->id,
            'requested_billing_cycle' => $cycle,
            'requested_at' => now(),
        ]);

        return $tenant;
    }

    public function cancel(Tenant $tenant): Tenant
    {
        $tenant->update(['requested_plan_id' => null, 'requested_billing_cycle' => null, 'requested_at' => null]);

        return $tenant;
    }
}
