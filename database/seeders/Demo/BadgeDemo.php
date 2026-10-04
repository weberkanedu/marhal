<?php

namespace Database\Seeders\Demo;

use App\Enums\Feature;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;

/**
 * Faz 3 / yaka kartı: demo acente Profesyonel pakette; yaka kartı Kurumsal'a ait olduğu için
 * staging'de görülebilsin diye bu acenteye özellik ayrıca açılır (paket değişmez).
 */
class BadgeDemo
{
    public function run(Tenant $tenant): string
    {
        TenantFeatureOverride::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'feature_key' => Feature::BadgeGeneration->value],
            ['enabled' => true],
        );

        return 'Demo acenteye yaka kartı özelliği açıldı.';
    }
}
