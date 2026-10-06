<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acente kimliği (10d): vergi dairesi / no ve Diyanet yetki no. Excel / PDF çıktılarında TÜRSAB no ile
 * birlikte yazar; hesabı başka acenteye veren kendi adıyla çıktı alamaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tax_office', 80)->nullable()->after('tursab_no');
            $table->string('tax_no', 20)->nullable()->after('tax_office');
            $table->string('diyanet_license_no', 40)->nullable()->after('tax_no');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['tax_office', 'tax_no', 'diyanet_license_no']);
        });
    }
};
