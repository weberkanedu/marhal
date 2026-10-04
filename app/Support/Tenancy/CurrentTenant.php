<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * İstek boyunca aktif acenteyi tutar. EnsureTenantContext middleware'i doldurur;
 * TenantScope ve BelongsToTenant buradan okur.
 */
final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Verilen acente bağlamında bir işlem çalıştırır (komutlar, kuyruk işleri, testler).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
