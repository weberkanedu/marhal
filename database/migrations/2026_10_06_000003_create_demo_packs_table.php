<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo acenteye yüklenmiş örnek veri paketleri (marhal:demo-data). Her paket bir kez yüklenir;
 * kullanıcı demo veriyi silse / değiştirse de bir sonraki yayında geri gelmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_packs', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('pack');
            $table->timestamp('applied_at');

            $table->primary(['tenant_id', 'pack']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_packs');
    }
};
