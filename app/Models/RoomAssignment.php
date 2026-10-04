<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir kaydın (yolcunun) bir konaklamadaki odası.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_hotel_id
 * @property string $room_id
 * @property string $registration_id
 * @property-read Room $room
 * @property-read Registration $registration
 */
class RoomAssignment extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
