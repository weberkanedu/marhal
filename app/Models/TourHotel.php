<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\TourHotelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Bir turun konaklaması: hangi otelde, hangi tarihlerde, hangi gruplar kalıyor.
 * Oda planı (Faz 2) her konaklama için ayrı yapılır (Mekke ve Medine ayrı).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property string $hotel_id
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property string|null $notes
 * @property-read Tour $tour
 * @property-read Hotel $hotel
 */
class TourHotel extends Model
{
    /** @use HasFactory<TourHotelFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
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
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Bu otelde kalan gruplar.
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_tour_hotel');
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
