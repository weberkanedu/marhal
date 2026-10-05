<?php

namespace Database\Seeders\Demo;

use App\Enums\BadgeSize;
use App\Models\BadgeSetting;
use App\Models\Group;
use App\Models\Tenant;
use App\Models\Tour;
use App\Support\GroupColors;

/**
 * Tasarım yenileme 5 örnek verisi: aktif turun gruplarına renk ve yaka kartı ayarı
 * (yatay boy, bütün alanlar, üç dilli arka yüz, sağlık notu açık — demo yolcuların rızası var).
 */
class BadgeRenewalDemo
{
    public function run(Tenant $tenant): string
    {
        $tour = Tour::query()->active()->orderBy('start_date')->first();
        $groups = $tour?->groups()->orderBy('name')->get() ?? collect();

        $groups->each(fn (Group $group, int $i) => $group->color === null
            ? $group->update(['color' => GroupColors::PALETTE[$i % count(GroupColors::PALETTE)]])
            : null);

        BadgeSetting::query()->firstOrNew()->fill([
            'size' => BadgeSize::Landscape,
            'fields' => BadgeSetting::FIELDS,
            'back_languages' => BadgeSetting::LANGUAGES,
            'back_side' => true,
            'health_note' => true,
        ])->save();

        return $groups->count().' gruba renk ve yaka kartı ayarı eklendi.';
    }
}
