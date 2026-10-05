<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir yolcunun bir uçuştaki kaydı (kişisel PNR ve bilet no; PNR boşsa uçuşun grup PNR'ı geçerli).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $flight_id
 * @property string $registration_id
 * @property string|null $pnr
 * @property string|null $ticket_no
 * @property string|null $seat_no
 * @property-read Flight $flight
 * @property-read Registration $registration
 */
class FlightPassenger extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected static function booted(): void
    {
        // Acente bağlamı olmayan yerlerde (komut, kuyruk) acente uçuştan alınır.
        static::creating(function (self $passenger): void {
            $passenger->tenant_id ??= Flight::withoutGlobalScopes()->whereKey($passenger->flight_id)->value('tenant_id');
        });
    }

    /**
     * @return BelongsTo<Flight, $this>
     */
    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
