<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yaka kartı yenileme: grup rengi (kart bandı ve otobüs tabelası) ve acentenin yaka kartı ayarları
 * (boy, ön yüz alanları, arka yüz dilleri, sağlık notu, QR). Ayar satırı yoksa varsayılanlar kullanılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('name');
        });

        Schema::create('badge_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('size', 20)->default('yatay');
            $table->json('fields');
            $table->json('back_languages');
            $table->boolean('back_side')->default(true);
            $table->boolean('health_note')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_settings');

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
