<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hesap paylaşımı koruması (10d): kullanıcının giriş yaptığı cihazlar (tarayıcıdaki kalıcı cihaz çerezinin
 * özeti saklanır, kendisi değil) ve "tek oturum" için kullanıcının en son giriş yaptığı cihaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('label', 120);
            $table->string('last_ip', 45)->nullable();
            $table->string('last_network', 45)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'token_hash']);
            $table->index(['user_id', 'last_seen_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('current_device_id')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('current_device_id');
        });

        Schema::dropIfExists('user_devices');
    }
};
