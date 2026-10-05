<?php

namespace App\Actions\Rooms;

use App\Models\TourHotel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Oteli tanımla": binanın kat sayısı (otel geneli, sonraki turlarda da kullanılır) ve bu konaklamada
 * bize verilen katlar. Odası olan bir kat listeden çıkarılamaz (önce odaları taşınmalı / silinmeli).
 */
class DefineStayFloors
{
    /**
     * @param  list<int>  $usedFloors
     */
    public function handle(TourHotel $stay, int $floorsCount, array $usedFloors): TourHotel
    {
        $used = array_values(array_unique(array_filter(array_map('intval', $usedFloors), fn (int $f) => $f >= 0 && $f <= $floorsCount)));
        sort($used);

        $roomFloors = $stay->rooms()->whereNotNull('floor')->pluck('floor')
            ->filter(fn ($f) => ctype_digit((string) $f))
            ->map(fn ($f) => (int) $f)
            ->unique();

        if ($missing = $roomFloors->diff($used)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'used_floors' => 'Bu katlarda oda var, listeden çıkarılamaz: '.implode(', ', $missing),
            ]);
        }

        return DB::transaction(function () use ($stay, $floorsCount, $used): TourHotel {
            $stay->hotel->update(['floors_count' => $floorsCount]);
            $stay->update(['used_floors' => $used]);

            return $stay;
        });
    }
}
