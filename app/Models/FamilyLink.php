<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yolcunun ailesine giden aile ekranı linki. Token şifreli saklanır (personel tekrar kopyalayabilir),
 * bulma token_hash ile yapılır. Yolcunun izni olmadan oluşturulmaz; iptal edilebilir, süresi biter.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $registration_id
 * @property string $token
 * @property string $token_hash
 * @property CarbonImmutable $consent_at
 * @property int|null $consent_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $last_viewed_at
 * @property int $view_count
 * @property-read Registration $registration
 */
class FamilyLink extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['token', 'token_hash'];

    /** @var list<string> erişim kaydına yazılmaz */
    protected array $auditRedacted = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'consent_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<FamilyLink>  $query
     * @return Builder<FamilyLink>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
