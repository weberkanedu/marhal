<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Otel kat planı: binanın kat sayısı (otel geneli), bu konaklamada kullandığımız katlar
 * (tur + otel; her turda farklı katlar verilebilir) ve asansöre yakın odalar
 * (hareket güçlüğü olan yolcular buraya önerilir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedSmallInteger('floors_count')->nullable()->after('stars');
        });

        Schema::table('tour_hotels', function (Blueprint $table) {
            $table->json('used_floors')->nullable()->after('check_out');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('near_elevator')->default(false)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('near_elevator'));
        Schema::table('tour_hotels', fn (Blueprint $table) => $table->dropColumn('used_floors'));
        Schema::table('hotels', fn (Blueprint $table) => $table->dropColumn('floors_count'));
    }
};
