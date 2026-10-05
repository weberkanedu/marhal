<?php

namespace App\Actions\Persons;

use App\Models\NeedType;
use App\Models\Person;
use App\Models\PersonNeed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * İhtiyaç profili ve sağlık verisi açık rızası (KVKK özel nitelikli veri):
 * - rıza olmadan ihtiyaç kaydedilemez,
 * - rıza geri alınınca ihtiyaç profili silinir,
 * - yalnız acentenin açık ihtiyaç türleri seçilebilir.
 */
class SavePersonNeeds
{
    public function grantConsent(Person $person, User $by): void
    {
        $person->forceFill(['health_consent_at' => now(), 'health_consent_by' => $by->getKey()])->save();
    }

    public function revokeConsent(Person $person): void
    {
        DB::transaction(function () use ($person): void {
            PersonNeed::query()->where('person_id', $person->getKey())->get()->each->delete();
            $person->forceFill(['health_consent_at' => null, 'health_consent_by' => null])->save();
        });
    }

    /**
     * @param  list<array{type_id: string, note?: string|null}>  $items
     */
    public function save(Person $person, array $items, User $by): void
    {
        if ($person->health_consent_at === null) {
            throw ValidationException::withMessages(['items' => 'Önce sağlık / ihtiyaç bilgisi için açık rıza alındığını işaretleyin.']);
        }

        $active = NeedType::query()->where('is_active', true)->pluck('id')->flip();
        $clean = collect($items)
            ->filter(fn (array $item) => $active->has($item['type_id']))
            ->unique('type_id')
            ->map(fn (array $item) => [
                'type_id' => $item['type_id'],
                'note' => filled($item['note'] ?? null) ? trim((string) $item['note']) : null,
            ])
            ->values()
            ->all();

        if (count($clean) !== count(collect($items)->unique('type_id'))) {
            throw ValidationException::withMessages(['items' => 'Geçersiz ihtiyaç türü.']);
        }

        $profile = PersonNeed::query()->firstOrNew(['person_id' => $person->getKey()]);

        if ($clean === []) {
            $profile->exists && $profile->delete();

            return;
        }

        $profile->forceFill([
            'tenant_id' => $person->tenant_id,
            'items' => $clean,
            'updated_by' => $by->getKey(),
        ])->save();
    }
}
