<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 2: oteller (acente geneli) ve turların grup bazında konaklamaları.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedTinyInteger('stars')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'city']);
        });

        Schema::create('tour_hotels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('hotel_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'check_in']);
        });

        // Konaklamanın hangi gruplar için olduğu (aynı turun grupları farklı otellerde kalabilir).
        Schema::create('group_tour_hotel', function (Blueprint $table) {
            $table->foreignUuid('tour_hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();

            $table->primary(['tour_hotel_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_tour_hotel');
        Schema::dropIfExists('tour_hotels');
        Schema::dropIfExists('hotels');
    }
};
