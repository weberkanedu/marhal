<?php

namespace App\Support;

use App\Models\Group;
use Illuminate\Support\Collection;

/**
 * Grup renkleri: yaka kartı bandı, otobüs tabelası ve ekranlarda grubun işareti. Renk seçilmemiş
 * gruplar turdaki sıralarına göre paletten renk alır (aynı turda iki grup aynı renge düşmesin).
 */
final class GroupColors
{
    public const PALETTE = ['#0f6b4e', '#9c3d2e', '#1d4f91', '#b07d12', '#6b3fa0', '#2f7d86'];

    /**
     * Turun gruplarına renk: grup id → renk.
     *
     * @param  Collection<int, Group>  $groups  (ad sırasıyla)
     * @return array<string, string>
     */
    public static function forGroups(Collection $groups): array
    {
        $used = $groups->pluck('color')->filter()->all();
        $free = array_values(array_diff(self::PALETTE, $used)) ?: self::PALETTE;
        $i = 0;

        return $groups->mapWithKeys(function (Group $group) use (&$i, $free): array {
            return [$group->id => $group->color ?? $free[$i++ % count($free)]];
        })->all();
    }
}
