<?php

namespace App\Actions\Buses;

use App\Enums\NeedEffect;
use App\Enums\RegistrationStatus;
use App\Models\Bus;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Support\Buses\BusLayout;
use App\Support\Needs\NeedProfiles;
use Illuminate\Support\Collection;

/**
 * Bir otobüste kimlerin oturması beklendiği ve yan koltuk uyarıları.
 */
class BusPassengers
{
    public function __construct(private readonly NeedProfiles $needs) {}

    /**
     * Otobüsün gruplarındaki aktif kayıtlar; turda başka bir otobüse oturtulmuş olanlar hariç.
     *
     * @return Collection<int, Registration>
     */
    public function expected(Bus $bus): Collection
    {
        return Registration::query()
            ->where('tour_id', $bus->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereIn('group_id', $bus->groups()->pluck('groups.id'))
            ->whereDoesntHave('seatAssignments', fn ($q) => $q->where('bus_id', '!=', $bus->getKey()))
            ->with(['person', 'group:id,name'])
            ->get();
    }

    /**
     * Uyarılar (engellemez): yan koltukta karşı cinsten akrabası olmayan yolcu; hareket güçlüğü olan
     * yolcu ön bölgede değil.
     *
     * @param  Collection<int, SeatAssignment>  $seats  otobüsün koltukları (registration.person yüklü)
     * @param  array<string, list<string>>  $links  aile bağları
     * @return array<int, list<string>> koltuk no → uyarılar
     */
    public function warnings(BusLayout $layout, Collection $seats, array $links): array
    {
        $bySeat = $seats->keyBy('seat_no');
        $warnings = [];
        $profiles = $this->needs->forPersons($seats->map(fn (SeatAssignment $s) => $s->registration->person_id));
        $front = $layout->frontZone();

        foreach ($bySeat as $seatNo => $seat) {
            $person = $seat->registration->person;

            if (NeedProfiles::has($profiles[$person->id] ?? [], NeedEffect::Mobility) && ! in_array((int) $seatNo, $front, true)) {
                $warnings[(int) $seatNo][] = 'Hareket güçlüğü var; ön bölgede (ilk sıralar) oturması önerilir';
            }

            foreach ($layout->neighbours((int) $seatNo) as $neighbourNo) {
                $neighbour = $bySeat->get($neighbourNo)?->registration->person;

                if ($neighbour !== null && $neighbour->gender !== $person->gender && ! in_array($neighbour->id, $links[$person->id] ?? [], true)) {
                    $warnings[(int) $seatNo][] = "Yanında karşı cinsten, akrabası olmayan yolcu ({$neighbour->full_name})";
                }
            }
        }

        return $warnings;
    }
}
