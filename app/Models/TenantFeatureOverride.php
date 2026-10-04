<?php

namespace App\Models;

use App\Support\Features\FeatureGate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paketten bağımsız olarak tek bir acenteye özellik açar / kapatır.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $feature_key
 * @property bool $enabled
 */
class TenantFeatureOverride extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $override) => app(FeatureGate::class)->flush($override->tenant_id));
        static::deleted(fn (self $override) => app(FeatureGate::class)->flush($override->tenant_id));
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
