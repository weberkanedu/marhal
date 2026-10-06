<?php

namespace App\Actions\Readiness;

use App\Models\Tour;

/**
 * Turun Ravza randevuları (erkekler / kadınlar, tarih-saat). Boş bırakılan randevu silinir.
 */
class SetRavzaAppointments
{
    public function handle(Tour $tour, ?string $men, ?string $women): void
    {
        $tour->update(['ravza_men_at' => $men ?: null, 'ravza_women_at' => $women ?: null]);
    }
}
