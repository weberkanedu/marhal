<?php

namespace App\Support\Needs;

use App\Enums\NeedCategory;
use App\Enums\NeedEffect;
use App\Models\NeedType;
use App\Models\Tenant;

/**
 * Yeni acenteye (ve tasarım yenilemede mevcut acentelere) kopyalanan ihtiyaç türleri.
 * Acente sonradan ad değiştirebilir, kapatabilir, kendi türünü ekleyebilir (Acente ayarları → İhtiyaç türleri).
 * Havayolu kodları IATA özel yardım / yemek kodlarıdır ("Özel yardım listesi"nde görünür).
 */
final class DefaultNeedTypes
{
    /**
     * @return list<array{name: string, category: NeedCategory, effect: NeedEffect|null, airline_code: string|null}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'Tekerlekli sandalye', 'category' => NeedCategory::Mobility, 'effect' => NeedEffect::Mobility, 'airline_code' => 'WCHS'],
            ['name' => 'Yürüme güçlüğü', 'category' => NeedCategory::Mobility, 'effect' => NeedEffect::Mobility, 'airline_code' => 'WCHR'],
            ['name' => 'Diyabet', 'category' => NeedCategory::Health, 'effect' => null, 'airline_code' => null],
            ['name' => 'Kalp hastalığı', 'category' => NeedCategory::Health, 'effect' => null, 'airline_code' => null],
            ['name' => 'Yüksek tansiyon', 'category' => NeedCategory::Health, 'effect' => null, 'airline_code' => null],
            ['name' => 'Düzenli ilaç kullanımı', 'category' => NeedCategory::Health, 'effect' => null, 'airline_code' => null],
            ['name' => 'Diyet yemeği', 'category' => NeedCategory::Diet, 'effect' => NeedEffect::Diet, 'airline_code' => 'SPML'],
            ['name' => 'Gıda alerjisi', 'category' => NeedCategory::Diet, 'effect' => NeedEffect::Diet, 'airline_code' => 'SPML'],
            ['name' => 'Refakatçi gerekir', 'category' => NeedCategory::Other, 'effect' => NeedEffect::Companion, 'airline_code' => null],
            ['name' => 'İşitme engeli', 'category' => NeedCategory::Other, 'effect' => null, 'airline_code' => 'DEAF'],
            ['name' => 'Görme engeli', 'category' => NeedCategory::Other, 'effect' => null, 'airline_code' => 'BLND'],
        ];
    }

    /**
     * Acenteye varsayılan türleri ekler (olanlar atlanır).
     */
    public static function seed(Tenant $tenant): void
    {
        foreach (self::all() as $i => $type) {
            $exists = NeedType::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('name', $type['name'])->exists();

            if (! $exists) {
                (new NeedType)->forceFill(['tenant_id' => $tenant->id, ...$type, 'sort' => $i])->save();
            }
        }
    }
}
