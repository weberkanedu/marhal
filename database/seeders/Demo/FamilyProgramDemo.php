<?php

namespace Database\Seeders\Demo;

use App\Actions\Family\CreateFamilyLink;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourProgramItem;
use App\Models\User;

/**
 * Tasarım yenileme 7a örnek verisi: sıradaki turun ilk günleri için örnek program (tasarımdaki gibi) ve
 * bir yolcuya aile ekranı linki (izin "demo" olarak işaretlenir). Link, çıktıda yazar.
 */
class FamilyProgramDemo
{
    public function run(Tenant $tenant): string
    {
        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'Aktif tur olmadığı için örnek program eklenmedi.';
        }

        $days = [
            1 => [['10:00', 'Cidde\'ye varış, otobüsle Mekke\'ye geçiş', null], ['21:00', 'Otele yerleşme', null]],
            2 => [['05:10', 'Sabah namazı', 'Harem-i Şerif'], ['10:00', 'Umre ibadeti, rehberle', 'Harem-i Şerif'], ['21:00', 'Serbest tavaf', null]],
            3 => [['05:10', 'Sabah namazı', 'Harem-i Şerif'], ['09:30', 'Ziyaret turu: Arafat, Müzdelife, Mina', null], ['14:00', 'Otelde dinlenme', null], ['20:30', 'Rehber sohbeti', 'Otel lobisi']],
            4 => [['05:10', 'Sabah namazı', 'Harem-i Şerif'], ['10:00', 'Cebel-i Nur ve Hira Mağarası', null], ['21:00', 'Serbest tavaf', null]],
        ];

        $count = 0;
        foreach ($days as $n => $events) {
            foreach ($events as [$time, $title, $place]) {
                TourProgramItem::query()->firstOrCreate(
                    ['tour_id' => $tour->id, 'day' => $tour->start_date->addDays($n - 1)->toDateString(), 'title' => $title],
                    ['time' => $time, 'place' => $place],
                );
                $count++;
            }
        }

        $registration = $tour->registrations()->where('status', '!=', 'iptal')->whereNotNull('group_id')->orderBy('created_at')->first();
        $admin = User::query()->where('tenant_id', $tenant->id)->where('role', 'admin')->first();
        $url = '';

        if ($registration instanceof Registration && $admin !== null) {
            $link = app(CreateFamilyLink::class)->handle($registration, true, $admin);
            $url = ' Aile linki: '.route('family.show', $link->token);
        }

        return "{$count} program etkinliği eklendi ({$tour->name}).{$url}";
    }
}
