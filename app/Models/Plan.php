<?php

namespace App\Models;

use App\Support\Features\FeatureGate;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satılan paket. Limitlerde null = sınırsız.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $tagline
 * @property string $price_monthly
 * @property string $price_yearly
 * @property string $currency
 * @property int|null $user_limit personel sınırı (rehberler sayılmaz)
 * @property int|null $passenger_limit abonelik yılı içindeki tur kaydı kotası
 * @property bool $is_public satışta mı
 * @property bool $is_featured paket kartlarında "en çok tercih edilen"
 * @property int $sort
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    /** Yıllık ödeyene 2 ay bizden: yıllık fiyat = aylık × 10. */
    public const YEARLY_MONTHS = 10;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'user_limit' => 'integer',
            'passenger_limit' => 'integer',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'sort' => 'integer',
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
