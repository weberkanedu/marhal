<?php

namespace Database\Seeders\Demo;

use App\Actions\Persons\AddPersonRelation;
use App\Actions\Rooms\AutoAssignRooms;
use App\Actions\Rooms\CreateRooms;
use App\Enums\Gender;
use App\Enums\HotelCity;
use App\Enums\RegistrationStatus;
use App\Enums\Relation;
use App\Enums\RoomKind;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Faz 2 / oda planı örnek verisi: oteller, grup bazında Mekke + Medine konaklaması,
 * aile yakınlıkları, odalar. Mekke otelleri otomatik dağıtılmış gelir; Medine boş bırakılır
 * ki kullanıcı "Otomatik dağıt"ı kendisi deneyebilsin.
 */
class RoomPlanDemo
{
    public function __construct(
        private readonly AddPersonRelation $addRelation,
        private readonly CreateRooms $createRooms,
        private readonly AutoAssignRooms $autoAssign,
    ) {}

    public function run(Tenant $tenant): string
    {
        $hotels = collect([
            ['Swissôtel Al Maqam', HotelCity::Mecca, 'Abraj Al Bait, Mekke'],
            ['Hilton Suites Makkah', HotelCity::Mecca, 'Jabal Omar, Mekke'],
            ['Pullman Zamzam Madinah', HotelCity::Medina, 'Mescid-i Nebevi güney avlusu, Medine'],
            ['Anwar Al Madinah Mövenpick', HotelCity::Medina, 'Mescid-i Nebevi batı, Medine'],
        ])->mapWithKeys(fn (array $h) => [$h[0] => Hotel::query()->firstOrCreate(
            ['name' => $h[0]],
            ['city' => $h[1], 'address' => $h[2], 'stars' => 5],
        )]);

        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'Oteller eklendi; aktif tur olmadığı için konaklama eklenmedi.';
        }

        /** @var Collection<int, Group> $groups */
        $groups = $tour->groups()->orderBy('name')->get();
        $half = $tour->start_date->addDays(intdiv((int) $tour->start_date->diffInDays($tour->end_date), 2));

        $this->relateFamilies($tour);

        // Aynı turun grupları Mekke'de farklı otellerde, Medine'de aynı otelde.
        $mecca = [];
        foreach ($groups as $index => $group) {
            $hotel = $hotels[$index % 2 === 0 ? 'Swissôtel Al Maqam' : 'Hilton Suites Makkah'];
            $mecca[] = $this->stay($tour, $hotel, $tour->start_date, $half, [$group]);
        }
        $medina = $this->stay($tour, $hotels['Pullman Zamzam Madinah'], $half, $tour->end_date, $groups->values()->all());

        foreach (array_filter($mecca) as $i => $stay) {
            $this->createRooms->handle($stay, (string) (501 + $i * 200), 4, 3, RoomKind::Male, (string) (5 + $i * 2));
            $this->autoAssign->apply($stay);
        }

        if ($medina !== null) {
            $this->createRooms->handle($medina, '301', 4, 4, RoomKind::Male, '3');
            $this->createRooms->handle($medina, '401', 2, 2, RoomKind::Family, '4');
        }

        return "Oteller, {$tour->name} için Mekke/Medine konaklaması, odalar ve yakınlıklar eklendi.";
    }

    /**
     * Konaklama ekler; grubu aynı gecelerde zaten bir otelde olanlar atlanır
     * (kullanıcının kendi eklediği konaklamalar bozulmasın).
     *
     * @param  array<int, Group>  $groups
     */
    private function stay(Tour $tour, Hotel $hotel, CarbonInterface $in, CarbonInterface $out, array $groups): ?TourHotel
    {
        $free = array_values(array_filter($groups, fn (Group $g) => ! $g->stays()
            ->whereDate('check_in', '<', $out)
            ->whereDate('check_out', '>', $in)
            ->exists()));

        if ($free === []) {
            return null;
        }

        $stay = $tour->stays()->create(['hotel_id' => $hotel->id, 'check_in' => $in, 'check_out' => $out]);
        $stay->groups()->sync(array_map(fn (Group $g) => $g->id, $free));

        return $stay;
    }

    /**
     * Her grupta bir karı-koca (aynı soyad) ve varsa bir anne-çocuk yakınlığı kurar (anne daha yaşlı olan).
     */
    private function relateFamilies(Tour $tour): void
    {
        $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereNotNull('group_id')
            ->with('person')
            ->get()
            ->groupBy('group_id')
            ->each(function (Collection $registrations): void {
                $persons = $registrations->map(fn (Registration $r) => $r->person);
                // En yaşlılar önce: anne, çocuğundan büyük olsun.
                $men = $persons->where('gender', Gender::Male)->sortBy('birth_date')->values();
                $women = $persons->where('gender', Gender::Female)->sortBy('birth_date')->values();

                if ($men->isNotEmpty() && $women->isNotEmpty()) {
                    $women[0]->update(['last_name' => $men[0]->last_name]);
                    $this->addRelation->handle($men[0], $women[0], Relation::Spouse);
                }

                if ($women->count() > 1) {
                    $this->addRelation->handle($women[1], $women[0], Relation::Mother);
                } elseif ($men->count() > 1 && $women->isNotEmpty() && $men[1]->birth_date > $women[0]->birth_date) {
                    $this->addRelation->handle($men[1], $women[0], Relation::Mother);
                }
            });
    }
}
