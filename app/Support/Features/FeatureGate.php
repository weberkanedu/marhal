<?php

namespace App\Support\Features;

use App\Enums\Feature;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Acentenin hangi modüllere erişebildiğini belirler:
 * tenant_feature_overrides varsa o, yoksa paketin plan_features kaydı (SPEC.md §2).
 */
class FeatureGate
{
    /**
     * @return list<string>
     */
    public function enabledFor(Tenant $tenant): array
    {
        return Cache::remember($this->cacheKey($tenant), now()->addHour(), function () use ($tenant): array {
            $features = $tenant->plan->features()
                ->pluck('enabled', 'feature_key')
                ->all();

            foreach ($tenant->featureOverrides()->pluck('enabled', 'feature_key') as $key => $enabled) {
                $features[$key] = $enabled;
            }

            return array_keys(array_filter($features));
        });
    }

    public function allows(Tenant $tenant, Feature|string $feature): bool
    {
        $key = $feature instanceof Feature ? $feature->value : $feature;

        return in_array($key, $this->enabledFor($tenant), true);
    }

    public function flush(Tenant|string $tenant): void
    {
        Cache::forget($this->cacheKey($tenant));
    }

    private function cacheKey(Tenant|string $tenant): string
    {
        $id = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return "tenant:{$id}:features";
    }
}
