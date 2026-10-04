<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 3: tur uçuşları ve uçuştaki yolcular (havayolu listesi, PNR, bilet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('airline', 100);
            $table->string('flight_no', 20);
            $table->string('departure_airport', 3);
            $table->string('arrival_airport', 3);
            $table->dateTime('departure_at');
            $table->dateTime('arrival_at');
            $table->string('pnr', 20)->nullable();
            $table->string('baggage', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'departure_at']);
        });

        Schema::create('flight_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('flight_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
            $table->string('pnr', 20)->nullable();
            $table->string('ticket_no', 30)->nullable();
            $table->timestamps();

            $table->unique(['flight_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_passengers');
        Schema::dropIfExists('flights');
    }
};
