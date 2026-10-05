<?php

namespace App\Reports\Definitions;

use App\Models\Person;
use App\Models\Registration;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Persons\PersonListFilter;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Yolcular ekranının çıktıları: "Yolcu listesi" ve "Pasaport kontrol listesi".
 * Ekrandaki süzgeç (?filtre=) aynen uygulanır. Kimlik / pasaport no yalnız yetkiliye tam yazılır.
 */
class PersonList
{
    public function build(?PersonListFilter $filter, bool $revealIds): Report
    {
        $rows = $this->persons($filter)->map(fn (Person $p) => [
            'last_name' => TurkishText::upper($p->last_name),
            'first_name' => $p->first_name,
            'gender' => $p->gender->label(),
            'birth_date' => $p->birth_date?->toDateString(),
            'phone' => $p->phone,
            'email' => $p->email,
            'national_id' => $revealIds ? $p->national_id : $p->masked_national_id,
            'passport_no' => $revealIds ? $p->passport_no : $p->masked_passport_no,
            'passport_expiry' => $p->passport_expiry_date?->toDateString(),
            'emergency' => trim(($p->emergency_contact_name ?? '').' '.($p->emergency_contact_phone ?? '')) ?: null,
            'kvkk' => $p->kvkk_consent_at ? 'Var' : 'Yok',
        ])->all();

        return new Report(
            key: 'persons',
            title: 'Yolcu Listesi',
            columns: [
                Column::text('last_name', 'Soyad', 11),
                Column::text('first_name', 'Ad', 10),
                Column::text('gender', 'Cinsiyet', 6),
                Column::date('birth_date', 'Doğum tarihi'),
                Column::text('phone', 'Telefon', 10),
                Column::text('email', 'E-posta', 14),
                Column::text('national_id', 'T.C. Kimlik No', 9),
                Column::text('passport_no', 'Pasaport No', 8),
                Column::date('passport_expiry', 'Pasaport bitiş'),
                Column::text('emergency', 'Acil durum', 14),
                Column::text('kvkk', 'KVKK', 5),
            ],
            rows: $rows,
            subtitle: $this->subtitle($filter, count($rows), $revealIds),
            landscape: true,
        );
    }

    public function passports(?PersonListFilter $filter, bool $revealIds): Report
    {
        $persons = $this->persons($filter);
        // Kişilerin aktif turları (aynı "Aktif turda" kuralı).
        $tours = PersonListFilter::OnTour->registrationScope(Registration::query())
            ->whereIn('person_id', $persons->modelKeys())
            ->with('tour:id,name')
            ->get()
            ->groupBy('person_id')
            ->map(fn (Collection $items) => $items->pluck('tour.name')->filter()->unique()->join(', '));

        $rows = $persons
            // Sorunlular üstte; kendi içinde ada göre (persons() ada göre sıralı, sıralama kararlı).
            ->sortBy(fn (Person $p) => $p->passportIssue() === null ? 1 : 0)
            ->values()
            ->map(fn (Person $p) => [
                'name' => TurkishText::upper($p->last_name).' '.$p->first_name,
                'passport_no' => $revealIds ? $p->passport_no : $p->masked_passport_no,
                'passport_issue' => $p->passport_issue_date?->toDateString(),
                'passport_expiry' => $p->passport_expiry_date?->toDateString(),
                'status' => $p->passportIssue() ?? 'Uygun',
                'tours' => $tours->get($p->id) ?: null,
                'phone' => $p->phone,
            ])->all();

        return new Report(
            key: 'persons_passports',
            title: 'Pasaport Kontrol Listesi',
            columns: [
                Column::text('name', 'Yolcu', 18),
                Column::text('passport_no', 'Pasaport No', 9),
                Column::date('passport_issue', 'Veriliş'),
                Column::date('passport_expiry', 'Bitiş'),
                Column::text('status', 'Durum', 11),
                Column::text('tours', 'Aktif tur', 18),
                Column::text('phone', 'Telefon', 10),
            ],
            rows: $rows,
            subtitle: [
                ...$this->subtitle($filter, count($rows), $revealIds),
                'Kural: bugünden itibaren en az 6 ay geçerli pasaport',
            ],
            landscape: true,
        );
    }

    /**
     * @return EloquentCollection<int, Person>
     */
    private function persons(?PersonListFilter $filter): EloquentCollection
    {
        return Person::query()
            ->when($filter, fn (Builder $q) => $filter?->apply($q))
            ->orderByName()
            ->get();
    }

    /**
     * @return list<string>
     */
    private function subtitle(?PersonListFilter $filter, int $count, bool $revealIds): array
    {
        return array_values(array_filter([
            $filter?->label(),
            "{$count} kişi",
            $revealIds ? null : 'Kimlik bilgileri maskelidir',
        ]));
    }
}
