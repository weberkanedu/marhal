<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Buses\BusLayout;
use Database\Factories\BusFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turun otobüsü. Koltuk düzeni oluşturulduğu araç tipinden kopyalanır (sonradan bozulmasın diye).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property string|null $vehicle_type_id
 * @property string $name
 * @property int $left_seats
 * @property int $right_seats
 * @property int $rows
 * @property int $back_row_seats
 * @property int|null $door_row
 * @property array<int, int|string>|null $reserved_seats
 * @property string|null $plate
 * @property string|null $driver_name
 * @property string|null $driver_phone
 * @property string|null $notes
 * @property-read Tour $tour
 * @property-read VehicleType|null $vehicleType
 */
class Bus extends Model
{
    /** @use HasFactory<BusFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    protected $table = 'buses';

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'left_seats' => 'integer',
            'right_seats' => 'integer',
            'rows' => 'integer',
            'back_row_seats' => 'integer',
            'door_row' => 'integer',
            'reserved_seats' => 'array',
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
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /**
     * Otobüste yolculuk eden gruplar (bir otobüste birden çok grup olabilir).
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'bus_group');
    }

    /**
     * @return HasMany<SeatAssignment, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(SeatAssignment::class);
    }

    public function layout(): BusLayout
    {
        return BusLayout::of($this);
    }

    /**
     * Rehber / görevli için ayrılmış, yolcu oturtulamayan koltuklar.
     *
     * @return list<int>
     */
    public function reserved(): array
    {
        return array_values(array_map('intval', $this->reserved_seats ?? []));
    }
}
