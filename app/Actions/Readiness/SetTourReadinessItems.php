<?php

namespace App\Actions\Readiness;

use App\Models\ReadinessItem;
use App\Models\Tour;
use Illuminate\Validation\ValidationException;

/**
 * Turda takip edilen hazırlık maddelerini seçer (tasarımdaki madde hapları). En az bir madde kalır;
 * yalnız acentenin açık maddeleri seçilebilir. İşaretlemeler silinmez (madde geri açılınca görünür).
 */
class SetTourReadinessItems
{
    /**
     * @param  list<string>  $itemIds
     */
    public function handle(Tour $tour, array $itemIds): void
    {
        $ids = ReadinessItem::query()->where('is_active', true)->whereIn('id', $itemIds)->pluck('id')->all();

        if ($ids === []) {
            throw ValidationException::withMessages(['item_ids' => 'En az bir hazırlık maddesi seçin.']);
        }

        $tour->readinessItems()->sync($ids);
    }
}
