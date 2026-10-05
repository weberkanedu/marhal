<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Araç gövdesi (otobüs / midibüs / minibüs / van) ve şoför yanı koltuk sayısı.
 * Otobüs, araç tipinin düzenini kopyalar (şablon değişince eski otobüs bozulmasın): iki tabloya da eklenir.
 * Mevcut kayıtlar: otobüs, şoför yanı koltuk yok — koltuk numaraları değişmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['vehicle_types', 'buses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('body', 20)->default('otobus')->after('door_row');
                $table->unsignedTinyInteger('front_seats')->default(0)->after('body');
            });
        }
    }

    public function down(): void
    {
        foreach (['vehicle_types', 'buses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['body', 'front_seats']);
            });
        }
    }
};
