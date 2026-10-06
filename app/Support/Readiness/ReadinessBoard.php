<?php

namespace App\Support\Readiness;

use App\Enums\Gender;
use App\Enums\ReadinessKind;
use App\Enums\ReadinessStatus;
use App\Enums\RegistrationStatus;
use App\Models\ReadinessCheck;
use App\Models\ReadinessItem;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * Turun hazırlık tablosu: takip edilen maddeler × yolcular. Tek kaynak: tur ekranındaki "Hazırlık"
 * sekmesi, tur / ana panel halkaları, ana paneldeki Ravza uyarısı ve "Hazırlık listesi" çıktısı buradan okur.
 *
 * Hücre: 'tamam' | 'sorun' | null. Pasaport ve fotoğraf maddeleri elle işaretlenmez, yolcu bilgisinden
 * hesaplanır (auto). Rehber için yalnız kendi gruplarının yolcuları ($groupIds).
 */
class ReadinessBoard
{
    /**
     * Turda takip edilen, açık maddeler (turda seçim yapılmamışsa "yeni turlarda seçili" olanlar).
     *
     * @return Collection<int, ReadinessItem>
     */
    public function items(Tour $tour): Collection
    {
        $selected = $tour->readinessItems()->where('is_active', true)->ordered()->get();

        return $selected->isNotEmpty()
            ? $selected->values()
            : ReadinessItem::query()->where('is_active', true)->where('default_on', true)->ordered()->get()->values();
    }

    /**
     * @param  list<string>|null  $groupIds  rehberin grupları (null: bütün tur)
     * @return array{
     *     items: list<array{id: string, name: string, kind: string, automatic: bool}>,
     *     rows: list<array{registration_id: string, name: string, gender: string, age: int|null, group_id: string|null, group_name: string|null, cells: array<string, array{status: string|null, auto: bool, title: string|null}>, done: int}>,
     *     ready: int,
     *     total: int,
     *     ravza: array{men: array{at: string|null, done: int, waiting: int}, women: array{at: string|null, done: int, waiting: int}}|null,
     * }
     */
    public function build(Tour $tour, ?array $groupIds = null): array
    {
        $items = $this->items($tour);
        $registrations = $this->registrations($tour, $groupIds);

        $checks = ReadinessCheck::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->whereIn('readiness_item_id', $items->pluck('id'))
            ->get()
            ->keyBy(fn (ReadinessCheck $c) => "{$c->registration_id}|{$c->readiness_item_id}");

        $rows = $registrations->map(function (Registration $r) use ($items, $checks, $tour): array {
            $cells = [];

            foreach ($items as $item) {
                $cells[$item->id] = $this->cell($item, $r, $checks->get("{$r->id}|{$item->id}"), $tour);
            }

            $person = $r->person;

            return [
                'registration_id' => $r->id,
                'name' => $person->full_name,
                'gender' => $person->gender->value,
                'age' => $person->birth_date ? (int) $person->birth_date->diffInYears($tour->start_date) : null,
                'group_id' => $r->group_id,
                'group_name' => $r->group?->name,
                'cells' => $cells,
                'done' => count(array_filter($cells, fn (array $c) => $c['status'] === ReadinessStatus::Done->value)),
            ];
        })->values();

        $ravzaItem = $items->first(fn (ReadinessItem $i) => $i->kind === ReadinessKind::Ravza);

        return [
            'items' => array_values($items->map(fn (ReadinessItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'kind' => $i->kind->value,
                'automatic' => $i->kind->isAutomatic(),
            ])->all()),
            'rows' => array_values($rows->all()),
            'ready' => $rows->filter(fn (array $row) => $items->isNotEmpty() && $row['done'] === $items->count())->count(),
            'total' => $rows->count(),
            'ravza' => $ravzaItem ? [
                'men' => $this->ravza($rows->map(fn (array $row) => [$row['gender'], $row['cells'][$ravzaItem->id]['status']]), Gender::Male, $tour->ravza_men_at?->format('Y-m-d\TH:i')),
                'women' => $this->ravza($rows->map(fn (array $row) => [$row['gender'], $row['cells'][$ravzaItem->id]['status']]), Gender::Female, $tour->ravza_women_at?->format('Y-m-d\TH:i')),
            ] : null,
        ];
    }

    /**
     * Ana panel ve tur halkası için kısa özet.
     *
     * @return array{ready: int, total: int, ravza_waiting: array{men: int, women: int}|null}
     */
    public function summary(Tour $tour): array
    {
        $board = $this->build($tour);

        return [
            'ready' => $board['ready'],
            'total' => $board['total'],
            'ravza_waiting' => $board['ravza'] ? [
                'men' => $board['ravza']['men']['waiting'],
                'women' => $board['ravza']['women']['waiting'],
            ] : null,
        ];
    }

    /**
     * @param  list<string>|null  $groupIds
     * @return Collection<int, Registration>
     */
    public function registrations(Tour $tour, ?array $groupIds = null): Collection
    {
        return $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->when($groupIds !== null, fn ($q) => $q->whereIn('group_id', $groupIds ?? []))
            ->with(['person', 'group:id,name'])
            ->get()
            ->sort(fn (Registration $a, Registration $b) => TurkishText::compare(
                $a->person->last_name.' '.$a->person->first_name,
                $b->person->last_name.' '.$b->person->first_name,
            ))
            ->values();
    }

    /**
     * @return array{status: string|null, auto: bool, title: string|null}
     */
    private function cell(ReadinessItem $item, Registration $registration, ?ReadinessCheck $check, Tour $tour): array
    {
        return match ($item->kind) {
            ReadinessKind::Passport => ($issue = $registration->person->passportIssue($tour->start_date)) === null
                ? ['status' => ReadinessStatus::Done->value, 'auto' => true, 'title' => 'Pasaport tur tarihinden 6 ay sonrasına kadar geçerli']
                : ['status' => ReadinessStatus::Problem->value, 'auto' => true, 'title' => $issue],
            ReadinessKind::Photo => $registration->person->photo_path !== null
                ? ['status' => ReadinessStatus::Done->value, 'auto' => true, 'title' => 'Fotoğrafı var']
                : ['status' => null, 'auto' => true, 'title' => 'Fotoğraf yok (yolcu sayfasından eklenir)'],
            default => [
                'status' => $check?->status->value,
                'auto' => false,
                'title' => $check ? $check->checked_at->format('d.m.Y H:i') : null,
            ],
        };
    }

    /**
     * @param  Collection<int, array{0: string, 1: string|null}>  $people  [cinsiyet, Ravza durumu]
     * @return array{at: string|null, done: int, waiting: int}
     */
    private function ravza(Collection $people, Gender $gender, ?string $at): array
    {
        $mine = $people->filter(fn (array $p) => $p[0] === $gender->value);
        $done = $mine->filter(fn (array $p) => $p[1] === ReadinessStatus::Done->value)->count();

        return ['at' => $at, 'done' => $done, 'waiting' => $mine->count() - $done];
    }
}
