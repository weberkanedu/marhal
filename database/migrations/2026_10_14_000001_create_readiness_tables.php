<?php

use App\Support\Readiness\DefaultReadinessItems;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Hazırlık takibi (tasarım yenileme 6):
 * - readiness_items: acentenin kontrol maddeleri (pasaport, aşı, vize, Nusuk, Ravza …; varsayılanlar kopyalanır),
 * - tour_readiness_items: turda hangi maddeler takip ediliyor (satır yoksa "yeni turlarda seçili" olanlar),
 * - readiness_checks: kayıt × madde durumu (tamam / sorun), kim ve ne zaman işaretledi,
 * - tours.ravza_men_at / ravza_women_at: Ravza randevuları,
 * - "readiness" modülü bütün paketlerde açık.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('kind', 20)->default('elle');
            $table->boolean('default_on')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('tour_readiness_items', function (Blueprint $table) {
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('readiness_item_id')->constrained()->cascadeOnDelete();

            $table->primary(['tour_id', 'readiness_item_id']);
        });

        Schema::create('readiness_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('readiness_item_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10);
            $table->timestamp('checked_at');
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['registration_id', 'readiness_item_id']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dateTime('ravza_men_at')->nullable();
            $table->dateTime('ravza_women_at')->nullable();
        });

        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('readiness_items')->insert(array_map(fn (array $item, int $i) => [
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'name' => $item['name'],
                'kind' => $item['kind']->value,
                'default_on' => $item['default_on'],
                'is_active' => true,
                'sort' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ], DefaultReadinessItems::all(), array_keys(DefaultReadinessItems::all())));
        }

        foreach (DB::table('plans')->pluck('id') as $planId) {
            DB::table('plan_features')->updateOrInsert(
                ['plan_id' => $planId, 'feature_key' => 'readiness'],
                ['id' => (string) Str::uuid7(), 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            Cache::forget("tenant:{$tenantId}:features");
        }
    }

    public function down(): void
    {
        DB::table('plan_features')->where('feature_key', 'readiness')->delete();

        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['ravza_men_at', 'ravza_women_at']);
        });

        Schema::dropIfExists('readiness_checks');
        Schema::dropIfExists('tour_readiness_items');
        Schema::dropIfExists('readiness_items');
    }
};
