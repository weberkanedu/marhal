<?php

namespace App\Models;

use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir tur / sefer (örn. "Ekim 2026 Umre Turu"). İçinde bir veya birden çok grup olur.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property TourType $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property TourStatus $status
 * @property int|null $capacity
 * @property string|null $default_price
 * @property string $currency
 * @property string|null $whatsapp_link
 * @property Carbon|null $ravza_men_at
 * @property Carbon|null $ravza_women_at
 */
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'type' => TourType::class,
            'status' => TourStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'capacity' => 'integer',
            'default_price' => 'decimal:2',
            'ravza_men_at' => 'datetime',
            'ravza_women_at' => 'datetime',
        ];
    }

    /**
     * Turda takip edilen hazırlık maddeleri (boşsa acentenin "yeni turlarda seçili" maddeleri).
     *
     * @return BelongsToMany<ReadinessItem, $this>
     */
    public function readinessItems(): BelongsToMany
    {
        return $this->belongsToMany(ReadinessItem::class, 'tour_readiness_items');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Turun alt grupları (her birinin kendi rehberi, otobüsü vb. olur).
     *
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Turun konaklamaları (Mekke / Medine otelleri, grup bazında).
     *
     * @return HasMany<TourHotel, $this>
     */
    public function stays(): HasMany
    {
        return $this->hasMany(TourHotel::class);
    }

    /**
     * @return HasMany<Flight, $this>
     */
    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    /**
     * @param  Builder<Tour>  $query
     * @return Builder<Tour>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TourStatus::active())->whereDate('end_date', '>=', today());
    }
}
