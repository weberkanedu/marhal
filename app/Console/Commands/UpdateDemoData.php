<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Demo\BusPlanDemo;
use Database\Seeders\Demo\FixParentAgesDemo;
use Database\Seeders\Demo\FlightDemo;
use Database\Seeders\Demo\RoomPlanDemo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo acenteye (demo-turizm) yeni özelliklerin örnek verisini yükler. Her paket bir kez yüklenir
 * (demo_packs tablosu); kullanıcının sonradan yaptığı ekleme / silmeler korunur.
 * Staging her açılışta otomatik çalıştırır (docker/entrypoint.d/60-demo-data.sh). Production'da çalışmaz.
 * Yeni faz geldikçe PACKS listesine paket eklenir.
 */
#[Signature('marhal:demo-data {--force : Production ortamında da çalıştır}')]
#[Description('Demo acenteye yeni özelliklerin örnek verisini yükler (her paket bir kez)')]
class UpdateDemoData extends Command
{
    /** @var array<string, class-string> paket adı → sınıf (run(Tenant): string) */
    public const PACKS = [
        'faz2-oda-plani' => RoomPlanDemo::class,
        'faz2-otobus-plani' => BusPlanDemo::class,
        'faz2-yakinlik-yas-duzeltme' => FixParentAgesDemo::class,
        'faz3-ucuslar' => FlightDemo::class,
    ];

    public function handle(CurrentTenant $currentTenant): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Production ortamında demo veri yüklenmez.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', 'demo-turizm')->first();

        if ($tenant === null) {
            $this->info('Demo acente yok; yüklenecek bir şey yok.');

            return self::SUCCESS;
        }

        foreach (self::PACKS as $pack => $class) {
            if (DB::table('demo_packs')->where('tenant_id', $tenant->id)->where('pack', $pack)->exists()) {
                continue;
            }

            $message = $currentTenant->run($tenant, fn () => DB::transaction(function () use ($tenant, $pack, $class): string {
                $result = app($class)->run($tenant);
                DB::table('demo_packs')->insert(['tenant_id' => $tenant->id, 'pack' => $pack, 'applied_at' => now()]);

                return $result;
            }));

            $this->info("[{$pack}] {$message}");
        }

        return self::SUCCESS;
    }
}
