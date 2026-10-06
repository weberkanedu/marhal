<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Turun gün gün programındaki bir etkinlik (ör. 3. gün 09:30 Ziyaret turu: Arafat, Müzdelife, Mina).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property CarbonImmutable $day
 * @property string|null $time
 * @property string $title
 * @property string|null $place
 * @property-read Tour $tour
 */
class TourProgramItem extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
