<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turun telefonla ön kayıt linki (acente WhatsApp'tan gönderir). Token şifreli, bulma token_hash ile.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property string $token
 * @property string $token_hash
 * @property bool $is_active
 * @property int $opened_count
 * @property int|null $created_by
 * @property-read Tour $tour
 */
class SignupLink extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['token', 'token_hash'];

    /** @var list<string> */
    protected array $auditRedacted = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'is_active' => 'boolean', 'opened_count' => 'integer'];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * @return HasMany<SignupRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(SignupRequest::class);
    }
}
