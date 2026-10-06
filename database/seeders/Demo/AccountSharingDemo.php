<?php

namespace Database\Seeders\Demo;

use App\Enums\SecurityAlertKind;
use App\Models\SecurityAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Str;

/**
 * Paketler 10d örnek verisi: demo acentenin kimliği (çıktı başlıklarında görünür; uydurma numaralar) ve
 * operasyon kullanıcısında örnek bir "kısa sürede çok cihazdan giriş" uyarısı (Platform → Acenteler'de görülsün).
 */
class AccountSharingDemo
{
    public function run(Tenant $tenant): string
    {
        $tenant->forceFill([
            'tursab_no' => $tenant->tursab_no ?? '0000',
            'tax_office' => $tenant->tax_office ?? 'Örnek',
            'tax_no' => $tenant->tax_no ?? '0000000000',
            'diyanet_license_no' => $tenant->diyanet_license_no ?? 'ÖRNEK-1',
        ])->save();

        $user = User::query()->where('tenant_id', $tenant->id)->where('role', 'operasyon')->first();

        if ($user === null) {
            return 'Acente kimliği yazıldı; operasyon kullanıcısı olmadığı için uyarı örneği eklenmedi.';
        }

        $labels = ['Chrome · Windows', 'Safari · iPhone / iPad', 'Chrome · Android', 'Edge · Windows', 'Firefox · Linux'];

        foreach ($labels as $i => $label) {
            UserDevice::query()->create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', Str::random(48)),
                'label' => $label,
                'last_network' => "10.20.{$i}",
                'last_seen_at' => now()->subMinutes(20 * $i),
            ]);
        }

        SecurityAlert::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'kind' => SecurityAlertKind::ManyDevices,
            'details' => ['devices' => count($labels), 'hours' => 2],
        ]);

        return "Acente kimliği yazıldı; {$user->name} için örnek şüpheli giriş uyarısı eklendi.";
    }
}
