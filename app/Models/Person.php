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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $passport_no_hash
 * @property Carbon|null $passport_issue_date
 * @property Carbon|null $passport_expiry_date
 * @property string|null $phone
 * @property string|null $email
 * @property Carbon|null $kvkk_consent_at
 * @property Carbon|null $health_consent_at
 * @property int|null $health_consent_by
 * @property-read string $full_name
 * @property-read string|null $masked_phone
 * @property-read string|null $masked_national_id
 * @property-read string|null $masked_passport_no
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'persons';

    protected $guarded = ['id', 'tenant_id', 'national_id_hash', 'passport_no_hash', 'health_consent_at', 'health_consent_by'];

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
            'health_consent_at' => 'datetime',
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
     * İhtiyaç profili (şifreli; okumak için App\Support\Needs\NeedProfiles).
     *
     * @return HasOne<PersonNeed, $this>
     */
    public function needs(): HasOne
    {
        return $this->hasOne(PersonNeed::class);
    }

    /**
     * Yakınları (her yakınlık iki yönlü saklanır; bu taraf: "ilgili kişi, bu kişinin X'i").
     *
     * @return HasMany<PersonRelation, $this>
     */
    public function relations(): HasMany
    {
        return $this->hasMany(PersonRelation::class);
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
     * Ad soyad (her kelime), telefon, T.C. Kimlik No veya pasaport no ile arama. Yolcular listesi ve
     * Ctrl K araması aynı kuralı kullanır.
     *
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $compact = preg_replace('/\s+/', '', $search) ?? '';

        return $query->where(function (Builder $query) use ($search, $compact): void {
            $query->where(function (Builder $query) use ($search): void {
                foreach (preg_split('/\s+/', trim($search)) ?: [] as $term) {
                    $query->where(fn (Builder $q) => $q
                        ->whereLike('first_name', "%{$term}%")
                        ->orWhereLike('last_name', "%{$term}%"));
                }
            });

            if (preg_match('/^\d{3,}$/', $compact)) {
                $query->orWhereLike('phone', "%{$compact}%");
            }

            if (preg_match('/^\d{11}$/', $compact)) {
                $query->orWhere(fn (Builder $q) => $q->whereNationalId($compact));
            }

            if (preg_match('/^[A-Za-z]+\d+$/', $compact)) {
                $query->orWhere(fn (Builder $q) => $q->wherePassportNo($compact));
            }
        });
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
     * Pasaport sorunu (yolcu listesi süzgeci, "Pasaport kontrol listesi"): pasaport no yok, bitiş tarihi yok
     * veya bugünden itibaren 6 aydan az geçerli. Tura özel kontrol FlightPassengers::warnings'te (tur tarihine göre).
     */
    public function passportIssue(?CarbonInterface $from = null): ?string
    {
        return match (true) {
            $this->passport_no_hash === null => 'Pasaport no yok',
            $this->passport_expiry_date === null => 'Bitiş tarihi yok',
            ! $this->passportValidFor($from ?? now()) => '6 aydan az geçerli',
            default => null,
        };
    }

    /**
     * passportIssue() ile aynı kural, sorgu olarak.
     *
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeWithPassportIssue(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('passport_no_hash')
            ->orWhereNull('passport_expiry_date')
            ->orWhereDate('passport_expiry_date', '<', now()->addMonths(6)->toDateString()));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Listelerde telefon: ilk 4 ve son 2 hane görünür (0532 *** ** 67); tamamı yolcu sayfasında.
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedPhone(): Attribute
    {
        return Attribute::get(function (): ?string {
            $digits = preg_replace('/\D/', '', (string) $this->phone) ?? '';

            return strlen($digits) < 7 ? $this->phone : substr($digits, 0, 4).' *** ** '.substr($digits, -2);
        });
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
