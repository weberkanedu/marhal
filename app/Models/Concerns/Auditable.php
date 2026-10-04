<?php

namespace App\Models\Concerns;

use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Oluşturma / güncelleme / silme işlemlerini audit_logs tablosuna yazar.
 * Modeldeki `$auditRedacted` alanlarının değeri yazılmaz, sadece adı yazılır.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => $model->writeAudit('create', $model->getAttributes()));
        static::updated(fn (self $model) => $model->writeAudit('update', $model->getChanges()));
        static::deleted(fn (self $model) => $model->writeAudit('delete'));
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    protected function writeAudit(string $action, ?array $attributes = null): void
    {
        $changes = null;

        if ($attributes !== null) {
            $redacted = property_exists($this, 'auditRedacted') ? $this->auditRedacted : [];
            $ignored = [$this->getKeyName(), 'tenant_id', 'created_at', 'updated_at'];

            $changes = collect($attributes)
                ->except($ignored)
                ->map(fn ($value, $key) => in_array($key, $redacted, true) && $value !== null ? '[gizli]' : $value)
                ->all();
        }

        app(AuditLogger::class)->log($action, $this, $changes ?: null);
    }
}
