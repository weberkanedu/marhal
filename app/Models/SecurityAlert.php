<?php

namespace App\Models;

use App\Enums\SecurityAlertKind;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Şüpheli kullanım uyarısı. Acente yöneticisi kendi acentesininkileri, platform hepsini görür.
 * Kural ve kapatma `App\Actions\Security` içinde.
 *
 * @property int $id
 * @property string|null $tenant_id
 * @property int $user_id
 * @property SecurityAlertKind $kind
 * @property array<string, mixed>|null $details
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Tenant|null $tenant
 */
class SecurityAlert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => SecurityAlertKind::class,
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    /**
     * Ekrandaki cümle: "2 saat içinde 5 farklı cihazdan girdi" gibi.
     */
    public function summary(): string
    {
        $d = $this->details ?? [];

        return match ($this->kind) {
            SecurityAlertKind::DeviceLimit => 'Kayıtlı cihaz sayısı '.($d['devices'] ?? '?').' oldu (sınır '.($d['limit'] ?? '?').').',
            SecurityAlertKind::ManyDevices => ($d['hours'] ?? 2).' saat içinde '.($d['devices'] ?? '?').' farklı cihazdan girdi.',
            SecurityAlertKind::ManyNetworks => ($d['hours'] ?? 2).' saat içinde '.($d['networks'] ?? '?').' farklı ağdan girdi.',
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
