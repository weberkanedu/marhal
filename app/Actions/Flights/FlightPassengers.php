<?php

namespace App\Actions\Flights;

use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Uçuşa yolcu ekleme kuralları, unvan (MR / MRS / CHD / INF) ve pasaport uyarıları.
 *
 * Engeller: başka turun / iptal edilmiş kaydı, aynı uçuşta iki kez, saatleri çakışan başka bir uçuşta olmak.
 * Uyarır (engellemez): pasaport no yok, pasaport dönüşten itibaren 6 aydan kısa geçerli.
 */
class FlightPassengers
{
    /**
     * Yolcuları uçuşa ekler; eklenemeyenleri nedeniyle döner (toplu eklemede biri yüzünden hepsi durmaz).
     *
     * @param  iterable<Registration>  $registrations
     * @return array{added: int, skipped: list<array{name: string, reason: string}>}
     */
    public function add(Flight $flight, iterable $registrations): array
    {
        $added = 0;
        $skipped = [];
        $already = $flight->passengers()->pluck('registration_id')->flip();
        $busy = $this->busy($flight);

        DB::transaction(function () use ($flight, $registrations, $already, $busy, &$added, &$skipped): void {
            foreach ($registrations as $registration) {
                $name = $registration->person->full_name;

                $reason = match (true) {
                    $registration->tour_id !== $flight->tour_id || $registration->status === RegistrationStatus::Cancelled => 'Bu turun aktif yolcusu değil',
                    $already->has($registration->id) => null,
                    isset($busy[$registration->id]) => "Aynı saatlerde {$busy[$registration->id]} uçuşunda",
                    default => false,
                };

                if ($reason === null) {
                    continue; // zaten uçuşta: sessizce atla
                }

                if ($reason !== false) {
                    $skipped[] = ['name' => $name, 'reason' => $reason];

                    continue;
                }

                $flight->passengers()->create(['registration_id' => $registration->id]);
                $added++;
            }
        });

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * Uçuş tarihindeki yaşa göre havayolu unvanı.
     */
    public function title(Person $person, CarbonInterface $date): string
    {
        $age = $person->birth_date?->diffInYears($date);

        return match (true) {
            $age !== null && $age < 2 => 'INF',
            $age !== null && $age < 12 => 'CHD',
            $person->gender === Gender::Male => 'MR',
            default => 'MRS',
        };
    }

    /**
     * @return list<string>
     */
    public function warnings(Person $person, Tour $tour): array
    {
        $warnings = [];

        if ($person->passport_no === null || $person->passport_no === '') {
            $warnings[] = 'Pasaport no yok';
        } elseif (! $person->passportValidFor($tour->end_date)) {
            $warnings[] = 'Pasaport dönüşten itibaren 6 aydan kısa geçerli';
        }

        return $warnings;
    }

    /**
     * Saatleri bu uçuşla çakışan diğer uçuşlardaki yolcular: registration_id → uçuş no.
     * Kaydedilmemiş (saatleri değiştirilmiş) uçuşla da çalışır.
     *
     * @return array<string, string>
     */
    public function busy(Flight $flight): array
    {
        return FlightPassenger::query()
            ->whereHas('flight', fn ($q) => $q
                ->where('tour_id', $flight->tour_id)
                ->whereKeyNot($flight->getKey())
                ->where('departure_at', '<', $flight->arrival_at)
                ->where('arrival_at', '>', $flight->departure_at))
            ->with('flight:id,flight_no')
            ->get()
            ->mapWithKeys(fn (FlightPassenger $p) => [$p->registration_id => $p->flight->flight_no])
            ->all();
    }

    /**
     * Verilen grupların aktif yolcuları (toplu ekleme için).
     *
     * @param  list<string>  $groupIds
     * @return Collection<int, Registration>
     */
    public function ofGroups(Flight $flight, array $groupIds): Collection
    {
        return Registration::query()
            ->where('tour_id', $flight->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereIn('group_id', $groupIds)
            ->with('person')
            ->get();
    }
}
