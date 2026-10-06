<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\Feature;
use App\Enums\SubscriptionState;
use App\Enums\TenantStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
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
 * @property BillingCycle|null $billing_cycle
 * @property Carbon|null $subscription_started_at
 * @property string|null $requested_plan_id
 * @property BillingCycle|null $requested_billing_cycle
 * @property Carbon|null $requested_at
 * @property Carbon|null $created_at
 * @property-read Plan $plan
 * @property-read Plan|null $requestedPlan
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    /** Dönem bitince salt okunura geçmeden önceki ek süre (gün). */
    public const GRACE_DAYS = 7;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'billing_cycle' => BillingCycle::class,
            'subscription_started_at' => 'datetime',
            'requested_billing_cycle' => BillingCycle::class,
            'requested_at' => 'datetime',
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
     * "Paketim"den gönderilen, ödeme bekleyen paket talebi.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->withoutGlobalScope(TenantScope::class);
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
     * Tarihlerden hesaplanan abonelik durumu: deneme / aktif → (dönem bitince 7 gün) gecikmede → salt okunur.
     * Bitiş tarihi boşsa süresiz. Askıda yalnız platform yöneticisinin elle seçtiği durumdur.
     */
    public function subscriptionState(): SubscriptionState
    {
        $now = Carbon::now();

        return match ($this->status) {
            TenantStatus::Suspended => SubscriptionState::Suspended,
            TenantStatus::Trial => $this->trial_ends_at === null || $this->trial_ends_at->greaterThan($now)
                ? SubscriptionState::Trial
                : SubscriptionState::ReadOnly,
            TenantStatus::Active => match (true) {
                $this->subscription_ends_at === null || $this->subscription_ends_at->greaterThan($now) => SubscriptionState::Active,
                $this->graceEndsAt()?->greaterThan($now) === true => SubscriptionState::PastDue,
                default => SubscriptionState::ReadOnly,
            },
        };
    }

    /**
     * Gecikmede iken salt okunura geçeceği an (dönem sonu + 7 gün).
     */
    public function graceEndsAt(): ?Carbon
    {
        return $this->subscription_ends_at?->addDays(self::GRACE_DAYS);
    }

    /**
     * Acente sisteme girebilir mi? Salt okunurda da girer (görür, indirir); yalnız askıdaki giremez.
     */
    public function isAccessible(): bool
    {
        return $this->subscriptionState() !== SubscriptionState::Suspended;
    }

    /**
     * Deneme bitti ya da ödeme ek süresi doldu: her şey görülür, hiçbir şey değiştirilemez. Veri silinmez.
     */
    public function isReadOnly(): bool
    {
        return $this->subscriptionState() === SubscriptionState::ReadOnly;
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

    /**
     * Personel sınırına sayılan kullanıcılar: aktif yönetici ve operasyon. Rehberler her pakette ücretsiz ve sınırsız.
     */
    public function staffCount(): int
    {
        return $this->users()
            ->where('is_active', true)
            ->where('role', '!=', UserRole::Guide)
            ->count();
    }

    public function canAddUser(): bool
    {
        $limit = $this->plan->user_limit;

        return $limit === null || $this->staffCount() < $limit;
    }

    public function activeTourCount(): int
    {
        return $this->tours()
            ->whereIn('status', TourStatus::active())
            ->whereDate('end_date', '>=', today())
            ->count();
    }
}
