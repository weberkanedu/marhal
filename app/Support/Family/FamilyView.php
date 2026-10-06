<?php

namespace App\Support\Family;

use App\Actions\Rooms\StayOccupancy;
use App\Enums\Gender;
use App\Models\FamilyLink;
use App\Models\Person;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\TourProgramItem;
use App\Support\Tours\TourJourney;
use Carbon\CarbonImmutable;

/**
 * Aile ekranının verisi (tasarımdaki "Ayşe Yılmaz'ın yolculuğu"): şu an nerede (şehir, otel, oda, yanında
 * kalan yakını), gün gün program, rehber ve acentenin acil telefonu. Kimlik, pasaport, sağlık ve ödeme
 * bilgisi gösterilmez; oda arkadaşlarından yalnız yolcunun aile bağı olanların adı görünür.
 * Program saatleri Suudi Arabistan saatiyle karşılaştırılır (geçmiş etkinlik soluk).
 */
class FamilyView
{
    public const TIMEZONE = 'Asia/Riyadh';

    public function __construct(
        private readonly TourJourney $journey,
        private readonly StayOccupancy $occupancy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(FamilyLink $link, Tenant $tenant, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(self::TIMEZONE);
        $today = $now->startOfDay();
        $registration = $link->registration->loadMissing(['person', 'tour', 'group.guide:id,name']);
        $person = $registration->person;
        $tour = $registration->tour;

        $phase = match (true) {
            $today->lt($tour->start_date) => 'before',
            $today->gt($tour->end_date) => 'after',
            default => 'during',
        };

        return [
            'name' => $person->full_name,
            'tour' => [
                'name' => $tour->name,
                'start' => $tour->start_date->toDateString(),
                'end' => $tour->end_date->toDateString(),
            ],
            'phase' => $phase,
            'days_left' => $phase === 'before' ? (int) $today->diffInDays($tour->start_date) : null,
            'now' => $this->now($registration->id, $person, $today),
            'next' => $phase === 'before' ? $this->firstStep($tour, $tenant) : null,
            'group' => $registration->group?->name,
            'guide' => $registration->group ? ($registration->group->guide_name ?? $registration->group->guide?->name) : null,
            'agency' => ['name' => $tenant->name, 'phone' => $tenant->phone],
            'days' => $this->days($registration->tour_id, $tour->start_date, $tour->end_date, $now),
            'today' => $today->toDateString(),
        ];
    }

    /**
     * Bugün kaldığı otel: şehir, otel, oda ve aynı odadaki yakınları ("Mehmet Bey ile birlikte").
     *
     * @return array{city: string, hotel: string, room: string|null, with: list<string>}|null
     */
    private function now(string $registrationId, Person $person, CarbonImmutable $today): ?array
    {
        $assignment = RoomAssignment::query()
            ->where('registration_id', $registrationId)
            ->whereHas('room.stay', fn ($q) => $q->whereDate('check_in', '<=', $today)->whereDate('check_out', '>', $today))
            ->with(['room.stay.hotel', 'room.assignments.registration.person'])
            ->first();

        if ($assignment === null) {
            $stay = TourHotel::query()
                ->whereHas('tour.registrations', fn ($q) => $q->whereKey($registrationId))
                ->whereDate('check_in', '<=', $today)->whereDate('check_out', '>', $today)
                ->with('hotel')
                ->first();

            return $stay ? ['city' => $stay->hotel->city->label(), 'hotel' => $stay->hotel->name, 'room' => null, 'with' => []] : null;
        }

        $roommates = $assignment->room->assignments
            ->map(fn (RoomAssignment $a) => $a->registration->person)
            ->reject(fn (Person $p) => $p->id === $person->id);
        $links = $this->occupancy->familyLinks([$person->id, ...$roommates->pluck('id')]);
        $family = $links[$person->id] ?? [];

        return [
            'city' => $assignment->room->stay->hotel->city->label(),
            'hotel' => $assignment->room->stay->hotel->name,
            'room' => $assignment->room->room_no,
            'with' => array_values($roommates
                ->filter(fn (Person $p) => in_array($p->id, $family, true))
                ->map(fn (Person $p) => $p->first_name.' '.($p->gender === Gender::Female ? 'Hanım' : 'Bey'))
                ->all()),
        ];
    }

    /**
     * Yolculuk başlamadan: ilk adım (gidiş uçuşu).
     *
     * @return array{title: string, detail: string|null, start: string}|null
     */
    private function firstStep(Tour $tour, Tenant $tenant): ?array
    {
        $step = collect($this->journey->for($tour, $tenant))->firstWhere('kind', 'outbound');

        return $step ? ['title' => $step['title'], 'detail' => $step['detail'], 'start' => (string) $step['start']] : null;
    }

    /**
     * Turun günleri ve her günün programı; geçmiş etkinlikler işaretli.
     *
     * @return list<array{date: string, label: string, events: list<array{time: string|null, title: string, place: string|null, past: bool}>}>
     */
    private function days(string $tourId, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $now): array
    {
        $items = TourProgramItem::query()
            ->where('tour_id', $tourId)
            ->orderBy('day')->orderByRaw('time IS NULL')->orderBy('time')
            ->get()
            ->groupBy(fn (TourProgramItem $i) => $i->day->toDateString());

        $days = [];
        for ($day = $start, $n = 1; $day->lte($end); $day = $day->addDay(), $n++) {
            $date = $day->toDateString();
            $days[] = [
                'date' => $date,
                'label' => "{$n}. gün",
                'events' => array_values(($items[$date] ?? collect())->map(fn (TourProgramItem $i) => [
                    'time' => $i->time ? substr($i->time, 0, 5) : null,
                    'title' => $i->title,
                    'place' => $i->place,
                    'past' => CarbonImmutable::parse("{$date} ".($i->time ?? '23:59'), self::TIMEZONE)->lt($now),
                ])->all()),
            ];
        }

        return $days;
    }
}
