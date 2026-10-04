<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 2: araç tipleri (acente geneli), tur otobüsleri ve koltuk planı.
 * Otobüs, oluşturulduğu andaki koltuk düzenini kendinde saklar; araç tipi sonradan
 * değişse bile mevcut koltuk numaraları bozulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $this->layoutColumns($table);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('buses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $this->layoutColumns($table);
            $table->json('reserved_seats')->nullable();
            $table->string('plate', 20)->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'name']);
        });

        // Otobüste hangi gruplar var (bir otobüste birden çok grup olabilir).
        Schema::create('bus_group', function (Blueprint $table) {
            $table->foreignUuid('bus_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();

            $table->primary(['bus_id', 'group_id']);
        });

        Schema::create('seat_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Tur tekrar tutulur: bir yolcu bir turda tek koltukta olabilir (veritabanı kısıtı).
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bus_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seat_no');
            $table->timestamps();

            $table->unique(['bus_id', 'seat_no']);
            $table->unique(['tour_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_assignments');
        Schema::dropIfExists('bus_group');
        Schema::dropIfExists('buses');
        Schema::dropIfExists('vehicle_types');
    }

    private function layoutColumns(Blueprint $table): void
    {
        $table->unsignedTinyInteger('left_seats');
        $table->unsignedTinyInteger('right_seats');
        $table->unsignedTinyInteger('rows');
        $table->unsignedTinyInteger('back_row_seats')->default(0);
        $table->unsignedTinyInteger('door_row')->nullable();
    }
};
