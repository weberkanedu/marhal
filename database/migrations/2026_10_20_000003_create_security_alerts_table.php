<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Şüpheli kullanım uyarıları (10d): cihaz sınırı aşıldı, kısa sürede çok cihazdan / çok ağdan giriş.
 * Önce uyarılır; acente yöneticisi ve platform görür, platform "Kullanıcıyı doğrula" ile kapatır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resolved_at', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
