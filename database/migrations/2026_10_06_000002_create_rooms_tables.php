<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 2: oda planı. Odalar konaklama (tur + otel) bazındadır; Mekke ve Medine planları ayrıdır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_hotel_id')->constrained()->cascadeOnDelete();
            $table->string('floor', 20)->nullable();
            $table->string('room_no', 20);
            $table->unsignedTinyInteger('capacity');
            $table->string('kind');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tour_hotel_id', 'room_no']);
        });

        Schema::create('room_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Konaklama tekrar tutulur: bir yolcu aynı konaklamada tek odada olabilir (veritabanı kısıtı).
            $table->foreignUuid('tour_hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tour_hotel_id', 'registration_id']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
        Schema::dropIfExists('rooms');
    }
};
