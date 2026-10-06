<?php

namespace App\Models;

use App\Enums\ReadinessKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Acentenin hazırlık maddesi (pasaport, aşı, vize …). Silinmez, kapatılır (işaretlemeler bozulmasın).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property ReadinessKind $kind
 * @property bool $default_on
 * @property bool $is_active
 * @property int $sort
 */
class ReadinessItem extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'kind' => ReadinessKind::class,
            'default_on' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<ReadinessItem>  $query
     * @return Builder<ReadinessItem>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }
}
