<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Buses\BusLayout;
use Database\Factories\VehicleTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acentenin tanımladığı araç tipi (koltuk düzeni şablonu): standart 2+2, VIP 2+1, midibüs …
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property int $left_seats
 * @property int $right_seats
 * @property int $rows
 * @property int $back_row_seats
 * @property int|null $door_row
 * @property string|null $notes
 */
class VehicleType extends Model
{
    /** @use HasFactory<VehicleTypeFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'left_seats' => 'integer',
            'right_seats' => 'integer',
            'rows' => 'integer',
            'back_row_seats' => 'integer',
            'door_row' => 'integer',
        ];
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    public function layout(): BusLayout
    {
        return BusLayout::of($this);
    }
}
