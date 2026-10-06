<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tasarım yenileme 7a:
 * - tour_program_items: turun gün gün programı (gün, saat, etkinlik, yer); aile ekranı ve "Tur programı" çıktısı okur,
 * - family_links: yolcunun ailesine giden link (aile ekranı). Token şifreli saklanır (personel tekrar kopyalayabilsin),
 *   arama özetle (token_hash) yapılır. Yolcunun izni (consent_at / consent_by) zorunlu; iptal edilebilir, süresi biter,
 * - "family_screen" modülü bütün paketlerde açık.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_program_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->time('time')->nullable();
            $table->string('title', 150);
            $table->string('place', 100)->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'day']);
        });

        Schema::create('family_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('consent_at');
            $table->foreignId('consent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (DB::table('plans')->pluck('id') as $planId) {
            DB::table('plan_features')->updateOrInsert(
                ['plan_id' => $planId, 'feature_key' => 'family_screen'],
                ['id' => (string) Str::uuid7(), 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            Cache::forget("tenant:{$tenantId}:features");
        }
    }

    public function down(): void
    {
        DB::table('plan_features')->where('feature_key', 'family_screen')->delete();
        Schema::dropIfExists('family_links');
        Schema::dropIfExists('tour_program_items');
    }
};
