<?php

namespace App\Reports\Definitions;

use App\Enums\RegistrationStatus;
use App\Models\Group;
use App\Models\Registration;
use App\Models\Tour;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\TurkishText;

/**
 * Tur (veya tek grup) yolcu listesi. Vize / havayolu / otel listesi için temel çıktı.
 * Kimlik ve pasaport no yalnızca yetkili kullanıcıya (yönetici) tam yazılır, diğerlerine maskeli.
 */
class TourPassengerList
{
    public function build(Tour $tour, ?Group $group, bool $revealIds): Report
    {
        $registrations = $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->when($group, fn ($q) => $q->where('group_id', $group?->getKey()))
            ->with(['person', 'group:id,name'])
            ->get()
            // Önce grup (grupsuzlar en sonda), sonra Türkçe alfabeyle soyad + ad.
            ->sort(fn (Registration $a, Registration $b) => ($a->group->name ?? "\u{FFFF}") <=> ($b->group->name ?? "\u{FFFF}")
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values();

        $rows = $registrations->map(function (Registration $r) use ($revealIds): array {
            $person = $r->person;

            return [
                'last_name' => TurkishText::upper($person->last_name),
                'first_name' => $person->first_name,
                'gender' => $person->gender->label(),
                'birth_date' => $person->birth_date?->toDateString(),
                'group' => $r->group?->name,
                'room_type' => $r->room_type?->label(),
                'phone' => $person->phone,
                'national_id' => $revealIds ? $person->national_id : $person->masked_national_id,
                'passport_no' => $revealIds ? $person->passport_no : $person->masked_passport_no,
                'passport_expiry' => $person->passport_expiry_date?->toDateString(),
                'status' => $r->status->label(),
                'emergency' => trim(($person->emergency_contact_name ?? '').' '.($person->emergency_contact_phone ?? '')) ?: null,
            ];
        })->all();

        return new Report(
            key: 'tour_passengers',
            title: $group ? "{$tour->name} — {$group->name} Yolcu Listesi" : "{$tour->name} Yolcu Listesi",
            columns: [
                Column::text('last_name', 'Soyad', 10),
                Column::text('first_name', 'Ad', 9),
                Column::text('gender', 'Cinsiyet', 5),
                Column::date('birth_date', 'Doğum tarihi'),
                Column::text('group', 'Grup', 6),
                Column::text('room_type', 'Oda', 6),
                Column::text('phone', 'Telefon', 8),
                Column::text('national_id', 'T.C. Kimlik No', 8),
                Column::text('passport_no', 'Pasaport No', 7),
                Column::date('passport_expiry', 'Pasaport bitiş'),
                Column::text('status', 'Durum', 8),
                Column::text('emergency', 'Acil durum', 12),
            ],
            rows: $rows,
            subtitle: [
                $tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y'),
                count($rows).' yolcu',
                ...($revealIds ? [] : ['Kimlik bilgileri maskelidir']),
            ],
            landscape: true,
        );
    }
}
