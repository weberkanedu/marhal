<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kişinin ihtiyaç profili (sağlık verisi). Kişi başına tek satır; hangi ihtiyaçlar ve notlar
 * birlikte şifreli saklanır (veritabanı dökümünde okunamaz). Okumak için App\Support\Needs\NeedProfiles.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $person_id
 * @property list<array{type_id: string, note: string|null}> $items
 * @property int|null $updated_by
 */
class PersonNeed extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    /**
     * Erişim kaydına içerik yazılmaz, sadece değiştiği.
     *
     * @var list<string>
     */
    protected array $auditRedacted = ['items'];

    protected function casts(): array
    {
        return [
            'items' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
