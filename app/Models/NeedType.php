<?php

namespace App\Models;

use App\Enums\NeedCategory;
use App\Enums\NeedEffect;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Acentenin ihtiyaç türü (tekerlekli sandalye, diyabet …). Silinmez, kapatılır (eski kayıtlar bozulmasın).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property NeedCategory $category
 * @property NeedEffect|null $effect
 * @property string|null $airline_code
 * @property bool $is_active
 * @property int $sort
 */
class NeedType extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'category' => NeedCategory::class,
            'effect' => NeedEffect::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
