<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tasarım yenileme 7b — telefonla ön kayıt:
 * - signup_links: turun ön kayıt linki (token şifreli + token_hash ile bulma; açılma sayısı; kapatılabilir),
 * - signup_requests: yolcunun gönderdiği başvuru. Kimlik / pasaport alanları ve ihtiyaçlar ŞİFRELİ (data, needs);
 *   KVKK ve sağlık rızası tarihleri; personel onaylar / reddeder (onaylanınca kişi + ön kayıt oluşur).
 *   Pasaport fotoğrafı sunucuya gelmez (tarayıcıda okunur).
 * - "online_signup" modülü bütün paketlerde açık.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('opened_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('signup_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('signup_link_id')->nullable()->constrained()->nullOnDelete();
            $table->text('data');
            $table->text('needs')->nullable();
            $table->boolean('read_from_passport')->default(false);
            $table->timestamp('kvkk_consent_at');
            $table->timestamp('health_consent_at')->nullable();
            $table->string('status', 15)->default('bekliyor');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignUuid('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tour_id', 'status']);
        });

        $now = now();

        foreach (DB::table('plans')->pluck('id') as $planId) {
            DB::table('plan_features')->updateOrInsert(
                ['plan_id' => $planId, 'feature_key' => 'online_signup'],
                ['id' => (string) Str::uuid7(), 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            Cache::forget("tenant:{$tenantId}:features");
        }
    }

    public function down(): void
    {
        DB::table('plan_features')->where('feature_key', 'online_signup')->delete();
        Schema::dropIfExists('signup_requests');
        Schema::dropIfExists('signup_links');
    }
};
