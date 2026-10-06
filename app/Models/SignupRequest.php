<?php

namespace App\Models;

use App\Enums\SignupStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yolcunun telefondan gönderdiği ön kayıt başvurusu. Kimlik / pasaport alanları (data) ve ihtiyaçlar (needs)
 * şifreli saklanır, erişim kaydına değerleri yazılmaz. Personel onaylayınca kişi + tura ön kayıt oluşur.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tour_id
 * @property string|null $signup_link_id
 * @property array{first_name: string, last_name: string, gender: string, birth_date: string|null, nationality: string, passport_no: string|null, passport_expiry_date: string|null, phone: string, email?: string|null} $data
 * @property list<string>|null $needs
 * @property bool $read_from_passport
 * @property CarbonImmutable $kvkk_consent_at
 * @property CarbonImmutable|null $health_consent_at
 * @property SignupStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $person_id
 * @property string|null $registration_id
 * @property CarbonImmutable $created_at
 * @property-read Tour $tour
 */
class SignupRequest extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected array $auditRedacted = ['data', 'needs'];

    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
            'needs' => 'encrypted:array',
            'read_from_passport' => 'boolean',
            'kvkk_consent_at' => 'datetime',
            'health_consent_at' => 'datetime',
            'status' => SignupStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
