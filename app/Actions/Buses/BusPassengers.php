<?php

namespace App\Actions\Buses;

use App\Enums\RegistrationStatus;
use App\Models\Bus;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Support\Buses\BusLayout;
use Illuminate\Support\Collection;

/**
 * Bir otobüste kimlerin oturması beklendiği ve yan koltuk uyarıları.
 */
class BusPassengers
{
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
     * Yan koltuktaki karşı cinsten, aile bağı olmayan yolcular için uyarı (engellemez).
     *
     * @param  Collection<int, SeatAssignment>  $seats  otobüsün koltukları (registration.person yüklü)
     * @param  array<string, list<string>>  $links  aile bağları
     * @return array<int, list<string>> koltuk no → uyarılar
     */
    public function warnings(BusLayout $layout, Collection $seats, array $links): array
    {
        $bySeat = $seats->keyBy('seat_no');
        $warnings = [];

        foreach ($bySeat as $seatNo => $seat) {
            $person = $seat->registration->person;

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
