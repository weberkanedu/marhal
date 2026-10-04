<?php

namespace App\Models;

use App\Enums\FlightDirection;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\FlightFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turun bir uçuşu (gidiş / dönüş / aktarma). Saatler havalimanının yerel saatidir.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property FlightDirection $direction
 * @property string $airline
 * @property string $flight_no
 * @property string $departure_airport
 * @property string $arrival_airport
 * @property Carbon $departure_at
 * @property Carbon $arrival_at
 * @property string|null $pnr
 * @property string|null $baggage
 * @property string|null $notes
 * @property-read Tour $tour
 */
class Flight extends Model
{
    /** @use HasFactory<FlightFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'direction' => FlightDirection::class,
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * @return HasMany<FlightPassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(FlightPassenger::class);
    }

    /**
     * Örn. "TK 92 · IST → JED".
     */
    public function title(): string
    {
        return "{$this->flight_no} · {$this->departure_airport} → {$this->arrival_airport}";
    }
}
