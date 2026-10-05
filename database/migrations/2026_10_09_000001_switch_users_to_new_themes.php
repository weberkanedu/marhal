<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasarım yenileme: üç eski tema + açık/koyu düğmesi yerine iki tema.
 * Gece Zümrüdü (koyu, varsayılan) ve Şafak (açık). Eski seçimler en yakın yeni temaya taşınır.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('theme', ['haremeyn', 'kum'])->update(['theme' => 'zumrut']);
        DB::table('users')->where('theme', 'kurumsal')->update(['theme' => 'safak']);
        DB::table('users')->whereNotIn('theme', ['zumrut', 'safak'])->update(['theme' => 'zumrut']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->default('zumrut')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('theme', 'zumrut')->update(['theme' => 'haremeyn']);
        DB::table('users')->where('theme', 'safak')->update(['theme' => 'kurumsal']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->default('haremeyn')->change();
        });
    }
};
