<?php

namespace App\Models;

use App\Support\Features\FeatureGate;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $price_monthly
 * @property string $price_yearly
 * @property string $currency
 * @property int|null $user_limit
 * @property int|null $active_tour_limit
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'user_limit' => 'integer',
            'active_tour_limit' => 'integer',
        ];
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function flushTenantFeatureCache(): void
    {
        $gate = app(FeatureGate::class);

        $this->tenants()->pluck('id')->each(fn (string $id) => $gate->flush($id));
    }
}
