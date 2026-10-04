<?php

namespace App\Models;

use App\Casts\EncryptedWithBlindIndex;
use App\Enums\Gender;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Security\SensitiveData;
use Carbon\CarbonImmutable as Carbon;
use Carbon\CarbonInterface;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acentenin müşterisi. Turdan bağımsızdır; tura katılımı Registration ile tutulur.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $first_name
 * @property string $last_name
 * @property Gender $gender
 * @property Carbon|null $birth_date
 * @property string $nationality
 * @property string|null $national_id
 * @property string|null $passport_no
 * @property Carbon|null $passport_issue_date
 * @property Carbon|null $passport_expiry_date
 * @property string|null $phone
 * @property string|null $email
 * @property Carbon|null $kvkk_consent_at
 * @property-read string $full_name
 * @property-read string|null $masked_national_id
 * @property-read string|null $masked_passport_no
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'persons';

    protected $guarded = ['id', 'tenant_id', 'national_id_hash', 'passport_no_hash'];

    /**
     * Hassas alanlar varsayılan olarak dışarı verilmez; maskeli halleri eklenir.
     *
     * @var list<string>
     */
    protected $hidden = ['national_id', 'national_id_hash', 'passport_no', 'passport_no_hash'];

    /**
     * @var list<string>
     */
    protected $appends = ['full_name', 'masked_national_id', 'masked_passport_no'];

    /**
     * @var list<string>
     */
    protected array $auditRedacted = ['national_id', 'national_id_hash', 'passport_no', 'passport_no_hash'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'kvkk_consent_at' => 'datetime',
            'national_id' => EncryptedWithBlindIndex::class,
            'passport_no' => EncryptedWithBlindIndex::class,
        ];
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * T.C. Kimlik No ile arama (şifreli alan, hash üzerinden).
     *
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeWhereNationalId(Builder $query, string $nationalId): Builder
    {
        return $query->where('national_id_hash', app(SensitiveData::class)->hash($nationalId));
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeWherePassportNo(Builder $query, string $passportNo): Builder
    {
        return $query->where('passport_no_hash', app(SensitiveData::class)->hash($passportNo));
    }

    /**
     * Soyad, ad sırası; PostgreSQL'de Türkçe alfabe (Ç, Ğ, İ, Ö, Ş, Ü doğru yerde).
     *
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeOrderByName(Builder $query): Builder
    {
        if ($this->getConnection()->getDriverName() === 'pgsql') {
            return $query
                ->orderByRaw('last_name COLLATE "tr-x-icu"')
                ->orderByRaw('first_name COLLATE "tr-x-icu"');
        }

        return $query->orderBy('last_name')->orderBy('first_name');
    }

    /**
     * Vize için pasaportun verilen tarihten itibaren en az 6 ay geçerli olması gerekir.
     */
    public function passportValidFor(CarbonInterface $date, int $months = 6): bool
    {
        return $this->passport_expiry_date !== null
            && $this->passport_expiry_date->gte($date->toImmutable()->addMonths($months));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function maskedNationalId(): Attribute
    {
        return Attribute::get(fn () => app(SensitiveData::class)->mask($this->national_id));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function maskedPassportNo(): Attribute
    {
        return Attribute::get(fn () => app(SensitiveData::class)->mask($this->passport_no, 3));
    }
}
