<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geri bildirim yanıtı (10c): yanıtı kim yazdı ve gönderen kullanıcı yanıtı ne zaman gördü
 * ("Görüşünü paylaş" düğmesindeki yeni yanıt noktası). Yanıt metni ve tarihi zaten tabloda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->foreignId('replied_by')->nullable()->after('replied_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reply_seen_at')->nullable()->after('replied_by');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('replied_by');
            $table->dropColumn('reply_seen_at');
        });
    }
};
