<?php

namespace App\Support\Persons;

use App\Enums\Concerns\HasOptions;
use App\Enums\RegistrationStatus;
use App\Models\Person;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Yolcular ekranının hazır süzgeçleri (?filtre=) ve sayı kartları. Yeni süzgeç = bir satır + apply().
 * Aynı süzgeçler "Çıktı al" listelerinde de kullanılır.
 */
enum PersonListFilter: string
{
    use HasOptions;

    case PassportIssue = 'pasaport';
    case OnTour = 'turda';
    case NoConsent = 'kvkk';
    case Needs = 'ihtiyac';

    public function label(): string
    {
        return match ($this) {
            self::PassportIssue => 'Pasaport sorunlu',
            self::OnTour => 'Aktif turda',
            self::NoConsent => 'KVKK onayı yok',
            self::Needs => 'Özel ihtiyaç',
        };
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::PassportIssue => $query->withPassportIssue(),
            self::OnTour => $query->whereHas('registrations', fn (Builder $q) => $this->registrationScope($q)),
            self::NoConsent => $query->whereNull('kvkk_consent_at'),
            self::Needs => $query->whereHas('needs'),
        };
    }

    /**
     * "Aktif turda" sayılan kayıt: iptal değil, tur aktif (satışta / kapandı ve bitmemiş).
     *
     * @param  Builder<Registration>  $query
     * @return Builder<Registration>
     */
    public function registrationScope(Builder $query): Builder
    {
        return $query->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereHas('tour', fn (Builder $t) => $t->active());
    }
}
