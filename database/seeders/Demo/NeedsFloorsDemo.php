<?php

namespace Database\Seeders\Demo;

use App\Models\NeedType;
use App\Models\PersonNeed;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Support\Needs\DefaultNeedTypes;

/**
 * Tasarım yenileme 4 örnek verisi: ihtiyaç türleri, üç yolcuya (açık rızalı) ihtiyaç, otellere kat bilgisi
 * ve her kattaki ilk iki odanın "asansöre yakın" işaretlenmesi. Yerleşimlere dokunmaz; uyarılar ekranda görünür.
 */
class NeedsFloorsDemo
{
    public function run(Tenant $tenant): string
    {
        DefaultNeedTypes::seed($tenant);
        $type = fn (string $name) => NeedType::query()->where('name', $name)->value('id');

        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'İhtiyaç türleri eklendi; aktif tur olmadığı için örnek ihtiyaç eklenmedi.';
        }

        $samples = [
            [['type_id' => $type('Yürüme güçlüğü'), 'note' => 'Bastonla yürüyor, merdiven çıkamıyor']],
            [['type_id' => $type('Diyabet'), 'note' => 'İnsülin kullanıyor'], ['type_id' => $type('Diyet yemeği'), 'note' => 'Şekersiz']],
            [['type_id' => $type('Tekerlekli sandalye'), 'note' => 'Kendi katlanır sandalyesi var']],
        ];

        $registrations = $tour->registrations()->with('person')->orderByDesc('created_at')->limit(count($samples))->get();

        $registrations->each(function (Registration $r, int $i) use ($samples, $tenant): void {
            $r->person->forceFill(['health_consent_at' => now()])->save();
            PersonNeed::query()->updateOrCreate(['person_id' => $r->person_id], ['items' => $samples[$i]])
                ->forceFill(['tenant_id' => $tenant->id])->save();
        });

        $stays = TourHotel::query()->where('tour_id', $tour->id)->with(['hotel', 'rooms'])->get();

        foreach ($stays as $stay) {
            $floors = $stay->rooms->pluck('floor')->filter(fn ($f) => ctype_digit((string) $f))->map(fn ($f) => (int) $f)->unique()->sort()->values();
            $stay->hotel->update(['floors_count' => $stay->hotel->floors_count ?? max(15, (int) $floors->max())]);
            $stay->update(['used_floors' => $floors->all()]);

            $stay->rooms->groupBy('floor')->each(fn ($rooms) => $rooms->sortBy('room_no', SORT_NATURAL)->take(2)
                ->each(fn (Room $room) => $room->update(['near_elevator' => true])));
        }

        return count($registrations).' yolcuya ihtiyaç, '.count($stays).' otele kat bilgisi eklendi.';
    }
}
