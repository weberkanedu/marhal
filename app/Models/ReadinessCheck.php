<?php

namespace App\Models;

use App\Enums\ReadinessStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir yolcunun (kayıt) bir hazırlık maddesindeki durumu. Satır yoksa madde bekliyor demektir.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $registration_id
 * @property string $readiness_item_id
 * @property ReadinessStatus $status
 * @property CarbonImmutable $checked_at
 * @property int|null $checked_by
 * @property-read Registration $registration
 * @property-read ReadinessItem $item
 */
class ReadinessCheck extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'status' => ReadinessStatus::class,
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<ReadinessItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ReadinessItem::class, 'readiness_item_id');
    }
}
