<?php

namespace App\Support\Needs;

use App\Enums\NeedEffect;
use App\Models\NeedType;
use App\Models\PersonNeed;

/**
 * İhtiyaç profillerini okumanın tek yolu (ekranlar, kurallar, listeler bunu kullanır).
 * Şifreli profiller bir kerede çözülür; ihtiyaç türleri acentenin tablosundan gelir.
 */
class NeedProfiles
{
    /** @var array<string, NeedType>|null */
    private ?array $types = null;

    /**
     * @param  iterable<string>  $personIds
     * @return array<string, array<int, array{type_id: string, name: string, category: string, effect: string|null, airline_code: string|null, note: string|null}>>
     */
    public function forPersons(iterable $personIds): array
    {
        $ids = collect($personIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $types = $this->types();

        return PersonNeed::query()
            ->whereIn('person_id', $ids)
            ->get()
            ->mapWithKeys(fn (PersonNeed $profile) => [$profile->person_id => collect($profile->items)
                ->filter(fn (array $item) => isset($types[$item['type_id']]))
                ->map(fn (array $item) => [
                    'type_id' => $item['type_id'],
                    'name' => $types[$item['type_id']]->name,
                    'category' => $types[$item['type_id']]->category->value,
                    'effect' => $types[$item['type_id']]->effect?->value,
                    'airline_code' => $types[$item['type_id']]->airline_code,
                    'note' => $item['note'] ?? null,
                ])
                ->sortBy(fn (array $item) => $types[$item['type_id']]->sort)
                ->values()
                ->all()])
            ->all();
    }

    /**
     * Rehbere / listeye gidecek kısa hâl: sadece ihtiyaç adları (not yok).
     *
     * @param  array<string, array<int, array{name: string}>>  $profiles
     * @return array<string, list<string>>
     */
    public static function labels(array $profiles): array
    {
        return array_map(fn (array $items) => array_column($items, 'name'), $profiles);
    }

    /**
     * @param  array<int, array{effect: string|null}>  $items
     */
    public static function has(array $items, NeedEffect $effect): bool
    {
        return in_array($effect->value, array_column($items, 'effect'), true);
    }

    /**
     * @return array<string, NeedType>
     */
    private function types(): array
    {
        return $this->types ??= NeedType::query()->get()->keyBy('id')->all();
    }
}
