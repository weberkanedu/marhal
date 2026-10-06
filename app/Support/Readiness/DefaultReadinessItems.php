<?php

namespace App\Support\Readiness;

use App\Enums\ReadinessKind;
use App\Models\ReadinessItem;
use App\Models\Tenant;

/**
 * Yeni acenteye (ve tasarım yenilemede mevcut acentelere) kopyalanan hazırlık maddeleri.
 * Acente sonradan ad değiştirebilir, kapatabilir, kendi maddesini ekleyebilir (Acente ayarları → Hazırlık maddeleri).
 * "default_on": yeni bir turda kendiliğinden takip edilen maddeler (turda haplardan değiştirilir).
 */
final class DefaultReadinessItems
{
    /**
     * @return list<array{name: string, kind: ReadinessKind, default_on: bool}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'Pasaport', 'kind' => ReadinessKind::Passport, 'default_on' => true],
            ['name' => 'Aşı', 'kind' => ReadinessKind::Manual, 'default_on' => true],
            ['name' => 'Vize', 'kind' => ReadinessKind::Visa, 'default_on' => true],
            ['name' => 'Nusuk', 'kind' => ReadinessKind::Nusuk, 'default_on' => true],
            ['name' => 'Ravza', 'kind' => ReadinessKind::Ravza, 'default_on' => true],
            ['name' => 'Fotoğraf', 'kind' => ReadinessKind::Photo, 'default_on' => false],
            ['name' => 'Sözleşme', 'kind' => ReadinessKind::Manual, 'default_on' => false],
            ['name' => 'İhram seti', 'kind' => ReadinessKind::Manual, 'default_on' => false],
        ];
    }

    /**
     * Acenteye varsayılan maddeleri ekler (olanlar atlanır).
     */
    public static function seed(Tenant $tenant): void
    {
        foreach (self::all() as $i => $item) {
            $exists = ReadinessItem::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('name', $item['name'])->exists();

            if (! $exists) {
                (new ReadinessItem)->forceFill(['tenant_id' => $tenant->id, ...$item, 'sort' => $i])->save();
            }
        }
    }
}
