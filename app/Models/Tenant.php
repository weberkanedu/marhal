<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\TenantStatus;
use App\Enums\TourStatus;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantScope;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sistemi kullanan acente.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $plan_id
 * @property TenantStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $subscription_ends_at
 * @property string|null $tursab_no
 * @property string $default_currency
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $address
 * @property string|null $logo_path
 * @property-read Plan $plan
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $tenant): void {
            if ($tenant->wasChanged('plan_id')) {
                app(FeatureGate::class)->flush($tenant);
            }
        });
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<TenantFeatureOverride, $this>
     */
    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }

    /**
     * @return HasMany<Tour, $this>
     */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Acente sistemi kullanabilir mi? (askıda değil, deneme süresi dolmamış)
     */
    public function isAccessible(): bool
    {
        return match ($this->status) {
            TenantStatus::Active => $this->subscription_ends_at === null || $this->subscription_ends_at->isFuture(),
            TenantStatus::Trial => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            TenantStatus::Suspended => false,
        };
    }

    public function hasFeature(Feature|string $feature): bool
    {
        return app(FeatureGate::class)->allows($this, $feature);
    }

    /**
     * @return list<string>
     */
    public function enabledFeatures(): array
    {
        return app(FeatureGate::class)->enabledFor($this);
    }

    public function canAddUser(): bool
    {
        $limit = $this->plan->user_limit;

        return $limit === null || $this->users()->count() < $limit;
    }

    public function activeTourCount(): int
    {
        return $this->tours()
            ->whereIn('status', TourStatus::active())
            ->whereDate('end_date', '>=', today())
            ->count();
    }

    public function canAddActiveTour(): bool
    {
        $limit = $this->plan->active_tour_limit;

        return $limit === null || $this->activeTourCount() < $limit;
    }
}
