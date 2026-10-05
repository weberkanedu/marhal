<?php

namespace App\Models;

use App\Enums\HotelCity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acentenin çalıştığı otel (Mekke / Medine / diğer). Turlar arasında tekrar kullanılır.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property HotelCity $city
 * @property string|null $address
 * @property string|null $phone
 * @property int|null $stars
 * @property int|null $floors_count
 * @property string|null $notes
 */
class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'city' => HotelCity::class,
            'stars' => 'integer',
            'floors_count' => 'integer',
        ];
    }

    /**
     * Bu otelin kullanıldığı tur konaklamaları.
     *
     * @return HasMany<TourHotel, $this>
     */
    public function stays(): HasMany
    {
        return $this->hasMany(TourHotel::class);
    }
}
