<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Görüşünü paylaş": acente kullanıcılarının ürün ekibine ilettiği öneri / hata / beğeni.
 * Numara (id) kullanıcıya takip numarası olarak gösterilir; yanıt platform panelinden yazılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('message');
            $table->string('screen', 255)->nullable();
            $table->string('status', 20)->default('yeni');
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
