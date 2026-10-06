<?php

namespace Database\Seeders\Demo;

use App\Enums\ReadinessStatus;
use App\Models\ReadinessCheck;
use App\Models\ReadinessItem;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Support\Readiness\DefaultReadinessItems;

/**
 * Tasarım yenileme 6 örnek verisi: hazırlık maddeleri; sıradaki turda aşı ve vize çoğunlukla tamam,
 * birkaç "sorun" ve eksik; Ravza randevuları (erkekler / kadınlar). Pasaport ve fotoğraf kendiliğinden dolar.
 */
class ReadinessDemo
{
    public function run(Tenant $tenant): string
    {
        DefaultReadinessItems::seed($tenant);

        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'Hazırlık maddeleri eklendi; aktif tur olmadığı için örnek işaretleme yapılmadı.';
        }

        $item = fn (string $name) => ReadinessItem::query()->where('name', $name)->firstOrFail();
        $registrations = $tour->registrations()->where('status', '!=', 'iptal')->orderBy('created_at')->get()->values();

        $count = 0;
        $registrations->each(function (Registration $r, int $i) use ($item, &$count): void {
            $marks = [
                'Aşı' => $i % 5 === 4 ? null : ($i % 7 === 3 ? ReadinessStatus::Problem : ReadinessStatus::Done),
                'Vize' => $i % 3 === 2 ? null : ReadinessStatus::Done,
                'Nusuk' => $i % 2 === 0 ? ReadinessStatus::Done : null,
                'Ravza' => $i % 4 === 0 ? ReadinessStatus::Done : null,
            ];

            foreach ($marks as $name => $status) {
                if ($status !== null) {
                    ReadinessCheck::query()->updateOrCreate(
                        ['registration_id' => $r->id, 'readiness_item_id' => $item($name)->id],
                        ['status' => $status, 'checked_at' => now()->subDays($i % 6)],
                    );
                    $count++;
                }
            }
        });

        $tour->update([
            'ravza_men_at' => $tour->start_date->addDays(6)->setTime(2, 0),
            'ravza_women_at' => $tour->start_date->addDays(6)->setTime(23, 0),
        ]);

        return "{$count} hazırlık işaretlemesi ve Ravza randevuları eklendi ({$tour->name}).";
    }
}
