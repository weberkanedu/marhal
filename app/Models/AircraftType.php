<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Flights\AircraftLayout;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Acentenin tanımladığı uçak tipi (kabin düzeni şablonu): A321neo 3-3, A330 2-4-2 …
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $cabin
 * @property int $first_row
 * @property int $last_row
 * @property list<int>|null $exit_rows
 * @property string|null $notes
 */
class AircraftType extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'first_row' => 'integer',
            'last_row' => 'integer',
            'exit_rows' => 'array',
        ];
    }

    /**
     * @return HasMany<Flight, $this>
     */
    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    public function layout(): AircraftLayout
    {
        return new AircraftLayout($this->cabin, $this->first_row, $this->last_row, array_map('intval', $this->exit_rows ?? []));
    }
}
