<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionState;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Platform yöneticisi "+7 gün": denemedeki acentenin deneme süresini, abonelerin dönem sonunu uzatır.
 * Süre, bitiş geçmişteyse bugünden sayılır; böylece salt okunur ya da gecikmedeki acente 7 gün açılır.
 */
class ExtendAccess
{
    public const DAYS = 7;

    public function handle(Tenant $tenant, int $days = self::DAYS): Tenant
    {
        if ($tenant->subscriptionState() === SubscriptionState::Suspended) {
            throw ValidationException::withMessages(['status' => 'Askıdaki acentenin süresi uzatılamaz; önce durumunu değiştirin.']);
        }

        $now = CarbonImmutable::now();
        $field = $tenant->status === TenantStatus::Trial ? 'trial_ends_at' : 'subscription_ends_at';
        $current = $tenant->{$field};

        // Süresiz (bitiş tarihi yok) acentede uzatılacak bir şey yok.
        if ($current === null) {
            return $tenant;
        }

        $tenant->update([$field => ($current->greaterThan($now) ? $current : $now)->addDays($days)]);

        return $tenant;
    }
}
