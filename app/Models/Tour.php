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
        ];
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
     * @param  Builder<Tour>  $query
     * @return Builder<Tour>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TourStatus::active())->whereDate('end_date', '>=', today());
    }
}
