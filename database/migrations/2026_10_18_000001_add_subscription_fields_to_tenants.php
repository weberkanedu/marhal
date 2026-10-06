<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonelik (10b): ödeme dönemi, abonelik başlangıcı (yolcu kotası yılı buna bağlı) ve acentenin
 * "Paketim"den gönderdiği bekleyen paket talebi (ödeme gelince platform yöneticisi açar).
 * Durum saklanmaz, tarihlerden hesaplanır (SubscriptionState).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_cycle', 10)->nullable()->after('subscription_ends_at');
            $table->timestamp('subscription_started_at')->nullable()->after('billing_cycle');
            $table->foreignUuid('requested_plan_id')->nullable()->after('subscription_started_at')
                ->constrained('plans')->nullOnDelete();
            $table->string('requested_billing_cycle', 10)->nullable()->after('requested_plan_id');
            $table->timestamp('requested_at')->nullable()->after('requested_billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_plan_id');
            $table->dropColumn(['billing_cycle', 'subscription_started_at', 'requested_billing_cycle', 'requested_at']);
        });
    }
};
