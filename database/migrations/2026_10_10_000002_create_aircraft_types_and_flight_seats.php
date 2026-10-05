<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uçak tipleri (acentenin tanımladığı kabin düzenleri) ve uçuş koltuk planı.
 * Uçuş, seçilen tipin düzenini kopyalar (tip değişince eski uçuş bozulmasın). Yolcunun koltuğu
 * havayoluna gönderilen koltuk tercih listesidir; "blocked_seats" başka yolculara ait (gri) koltuklardır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cabin', 20);
            $table->unsignedTinyInteger('first_row')->default(1);
            $table->unsignedTinyInteger('last_row');
            $table->json('exit_rows')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->foreignUuid('aircraft_type_id')->nullable()->after('arrival_at')->constrained()->nullOnDelete();
            $table->string('cabin', 20)->nullable()->after('aircraft_type_id');
            $table->unsignedTinyInteger('first_row')->nullable()->after('cabin');
            $table->unsignedTinyInteger('last_row')->nullable()->after('first_row');
            $table->json('exit_rows')->nullable()->after('last_row');
            $table->json('blocked_seats')->nullable()->after('exit_rows');
        });

        Schema::table('flight_passengers', function (Blueprint $table) {
            $table->string('seat_no', 4)->nullable()->after('ticket_no');
            $table->unique(['flight_id', 'seat_no']);
        });
    }

    public function down(): void
    {
        Schema::table('flight_passengers', function (Blueprint $table) {
            $table->dropUnique(['flight_id', 'seat_no']);
            $table->dropColumn('seat_no');
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aircraft_type_id');
            $table->dropColumn(['cabin', 'first_row', 'last_row', 'exit_rows', 'blocked_seats']);
        });

        Schema::dropIfExists('aircraft_types');
    }
};
