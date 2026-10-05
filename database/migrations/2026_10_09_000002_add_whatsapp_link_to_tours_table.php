<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Turun WhatsApp grubunun davet bağlantısı (tur sayfasındaki "WhatsApp grubu" düğmesi kopyalar).
            $table->string('whatsapp_link')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('whatsapp_link');
        });
    }
};
