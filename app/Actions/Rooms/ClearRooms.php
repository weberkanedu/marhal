<?php

namespace App\Actions\Rooms;

use App\Models\TourHotel;
use Illuminate\Support\Facades\DB;

/**
 * Otel planını temizler ("Temizle"): bu konaklamadaki bütün yolcular odalarından çıkar, odalar kalır.
 * Her çıkarma tek tek silinir ki erişim kaydına yazılsın.
 */
class ClearRooms
{
    public function handle(TourHotel $stay): int
    {
        return DB::transaction(fn (): int => $stay->roomAssignments()->get()->each->delete()->count());
    }
}
