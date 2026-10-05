<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir turun içindeki grup (örn. "A Grubu"). Rehber, otobüs ve oda organizasyonu
 * grup bazında yapılır.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property string $name
 * @property string|null $color
 * @property int|null $guide_user_id
 * @property string|null $guide_name
 * @property string|null $guide_phone
 * @property string|null $notes
 * @property-read Tour $tour
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Sistemde kullanıcısı olan rehber (rol: rehber).
     *
     * @return BelongsTo<User, $this>
     */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guide_user_id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Grubun kaldığı oteller (konaklamalar).
     *
     * @return BelongsToMany<TourHotel, $this>
     */
    public function stays(): BelongsToMany
    {
        return $this->belongsToMany(TourHotel::class, 'group_tour_hotel');
    }
}
