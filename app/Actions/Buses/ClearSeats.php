<?php

namespace App\Actions\Buses;

use App\Models\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Otobüsün koltuk planını temizler ("Temizle"): bütün yolcular koltuktan kalkar, rehbere ayrılan
 * koltuklar ayrılmış kalır. Her kaldırma erişim kaydına yazılır.
 */
class ClearSeats
{
    public function handle(Bus $bus): int
    {
        return DB::transaction(fn (): int => $bus->seats()->get()->each->delete()->count());
    }
}
