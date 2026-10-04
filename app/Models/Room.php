<?php

namespace App\Models;

use App\Enums\RoomKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir konaklamadaki (tur + otel) oda.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_hotel_id
 * @property string|null $floor
 * @property string $room_no
 * @property int $capacity
 * @property RoomKind $kind
 * @property string|null $notes
 * @property-read TourHotel $stay
 */
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'kind' => RoomKind::class,
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TourHotel, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(TourHotel::class, 'tour_hotel_id');
    }

    /**
     * @return HasMany<RoomAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }
}
