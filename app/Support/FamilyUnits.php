<?php

namespace App\Support;

use App\Models\Registration;

/**
 * Aile bağıyla birbirine bağlı yolcu kümeleri (oda ve koltuk otomatik dağıtımı için).
 * Küme içi sıra: her kişi kendinden önceki birine bağlıdır, böylece sırayla yerleştirirken
 * aile odası kuralı da sağlanır.
 */
final class FamilyUnits
{
    /**
     * @param  list<Registration>  $registrations
     * @param  array<string, list<string>>  $links  person_id → aile bağı olan person_id'ler
     * @return list<list<Registration>>
     */
    public static function build(array $registrations, array $links): array
    {
        $byPerson = [];
        foreach ($registrations as $registration) {
            $byPerson[$registration->person_id] = $registration;
        }

        $seen = [];
        $units = [];

        foreach ($registrations as $registration) {
            if (isset($seen[$registration->person_id])) {
                continue;
            }

            $unit = [];
            $queue = [$registration->person_id];
            $seen[$registration->person_id] = true;

            while ($queue !== []) {
                $personId = array_shift($queue);
                $unit[] = $byPerson[$personId];

                foreach ($links[$personId] ?? [] as $relatedId) {
                    if (isset($byPerson[$relatedId]) && ! isset($seen[$relatedId])) {
                        $seen[$relatedId] = true;
                        $queue[] = $relatedId;
                    }
                }
            }

            $units[] = $unit;
        }

        return $units;
    }

    /**
     * @param  list<Registration>  $unit
     */
    public static function isMixedGender(array $unit): bool
    {
        return count(array_unique(array_map(fn (Registration $r) => $r->person->gender->value, $unit))) > 1;
    }
}
