<?php

namespace App\Support\Plans;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\Tenant;
use App\Support\Tenancy\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Paketin yıllık yolcu kotası (kullanıcı kararı 2026-10-07): abonelik yılı içinde turlara yapılan,
 * iptal edilmemiş kayıtlar sayılır; iptal ya da silinen kayıt kotaya geri döner. Yıl yenilenince sıfırlanır.
 *
 * Abonelik yılı: aboneliğin başladığı günün (yoksa acentenin açıldığı günün) yıl dönümlerinden bugünü
 * içine alanı. Aylık ödemede dönem her ay uzasa da kota yılı kaymaz. Ana panel, platform paneli ve kayıt kuralı (RegisterPerson) bu sınıfı kullanır.
 */
class PassengerQuota
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [başlangıç, bitiş)
     */
    public function period(Tenant $tenant): array
    {
        $now = CarbonImmutable::now();
        $start = CarbonImmutable::instance($tenant->subscription_started_at ?? $tenant->created_at ?? $now);

        while ($start->greaterThan($now)) {
            $start = $start->subYear();
        }
        while ($start->addYear()->lessThanOrEqualTo($now)) {
            $start = $start->addYear();
        }

        return [$start, $start->addYear()];
    }

    public function used(Tenant $tenant): int
    {
        [$start] = $this->period($tenant);

        return Registration::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->getKey())
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->where('registered_at', '>=', $start)
            ->count();
    }

    /**
     * @return array{used: int, limit: int|null, renews_at: string}
     */
    public function summary(Tenant $tenant): array
    {
        return [
            'used' => $this->used($tenant),
            'limit' => $tenant->plan->passenger_limit,
            'renews_at' => $this->period($tenant)[1]->toDateString(),
        ];
    }

    /**
     * Yeni (ya da iptalden geri açılan) bir kayıt için kotada yer var mı; yoksa kayıt engellenir.
     */
    public function ensureRoom(Tenant $tenant, string $field = 'person_id'): void
    {
        $limit = $tenant->plan->passenger_limit;

        if ($limit !== null && $this->used($tenant) >= $limit) {
            $renews = $this->period($tenant)[1]->translatedFormat('j F Y');

            throw ValidationException::withMessages([
                $field => "Paketinizin yıllık yolcu kotası doldu ({$limit} kişi). Kota {$renews} tarihinde yenilenir; ".
                    'iptal edilen kayıtlar kotaya geri döner. Daha fazla yolcu için paketinizi yükseltebilirsiniz.',
            ]);
        }
    }
}
