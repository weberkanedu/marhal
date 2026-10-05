<?php

namespace App\Reports\Definitions;

use App\Enums\RegistrationStatus;
use App\Models\BadgeSetting;
use App\Models\Group;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\GroupColors;
use App\Support\Media\PersonPhotoStore;
use App\Support\Needs\NeedProfiles;
use App\Support\Placements;
use App\Support\TurkishText;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Yaka kartı verisi (tur, grup veya tek yolcu). Kart başına: ad soyad, fotoğraf, grup ve rengi, rehber,
 * oteller ve oda (Placements — tur tablosuyla aynı kaynak), otobüs koltuğu, seri no, QR; arka yüz için
 * isteğe bağlı sağlık notu (yalnız acente açtıysa ve yolcunun açık rızası varsa).
 * Kimlik / pasaport bilgisi yazılmaz. Görünüm: resources/views/reports/badges.blade.php.
 */
class TourBadges
{
    public function __construct(
        private readonly PersonPhotoStore $photos,
        private readonly NeedProfiles $needs,
    ) {}

    /**
     * @return list<array{
     *     name: string, first_name: string, last_name: string, group: string|null, color: string,
     *     guide: string|null, photo: string|null, initials: string,
     *     hotels: list<array{city: string, hotel: string, room: string|null, address: string|null}>,
     *     bus: array{label: string, value: string}|null, serial: string, qr: string|null, health: string|null,
     * }>
     */
    public function build(Tour $tour, ?Group $group = null, ?Registration $only = null, ?BadgeSetting $settings = null): array
    {
        $settings ??= BadgeSetting::current();

        $registrations = $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->when($group, fn ($q) => $q->where('group_id', $group?->getKey()))
            ->when($only, fn ($q) => $q->whereKey($only?->getKey()))
            ->with(['person', 'group', ...Placements::ROOM_RELATIONS, ...Placements::SEAT_RELATIONS])
            ->get()
            ->sort(fn (Registration $a, Registration $b) => ($a->group->name ?? "\u{FFFF}") <=> ($b->group->name ?? "\u{FFFF}")
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values();

        $groupStays = $tour->stays()->with(['hotel', 'groups:id'])->orderBy('check_in')->get();
        $colors = GroupColors::forGroups($tour->groups()->orderBy('name')->get());
        $health = $settings->health_note
            ? $this->needs->forPersons($registrations->filter(fn (Registration $r) => $r->person->health_consent_at !== null)->pluck('person_id'))
            : [];
        $agency = $tour->tenant()->first();

        return array_values($registrations->map(function (Registration $r) use ($groupStays, $colors, $health, $settings, $agency): array {
            $person = $r->person;
            $group = $r->group;
            $serial = strtoupper(substr(str_replace('-', '', $r->id), -6));

            return [
                'name' => TurkishText::upper($person->first_name.' '.$person->last_name),
                'first_name' => TurkishText::upper($person->first_name),
                'last_name' => TurkishText::upper($person->last_name),
                'group' => $group?->name,
                'color' => $group ? ($colors[$group->id] ?? GroupColors::PALETTE[0]) : '#444444',
                'guide' => $group && ($group->guide_name || $group->guide_phone)
                    ? trim(($group->guide_name ?? '').' '.($group->guide_phone ?? ''))
                    : null,
                'photo' => $settings->shows('photo') ? $this->photos->dataUri($person) : null,
                'initials' => TurkishText::upper(mb_substr($person->first_name, 0, 1).mb_substr($person->last_name, 0, 1)),
                'hotels' => $settings->shows('hotels') ? Placements::hotels($r, $groupStays) : [],
                'bus' => $settings->shows('bus') ? Placements::seat($r) : null,
                'serial' => $serial,
                'qr' => $settings->shows('qr') ? $this->qr($person->full_name, $agency?->name, $agency?->phone, $serial) : null,
                'health' => isset($health[$person->id])
                    ? implode(' · ', array_map(fn (array $i) => $i['name'].($i['note'] ? " ({$i['note']})" : ''), $health[$person->id]))
                    : null,
            ];
        })->all());
    }

    /**
     * Kartı bulan kişinin telefonla okuyabileceği QR: yolcu, acente, acil telefon, kart no.
     * (Aile ekranı gelince bağlantı da eklenecek.)
     */
    private function qr(string $name, ?string $agency, ?string $phone, string $serial): string
    {
        $text = implode("\n", array_filter([$name, $agency, $phone ? "Acil / Emergency: {$phone}" : null, "Kart / Card: {$serial}"]));
        $svg = (new Writer(new ImageRenderer(new RendererStyle(160, 1), new SvgImageBackEnd)))->writeString($text, 'UTF-8');

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
