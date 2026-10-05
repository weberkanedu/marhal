<?php

use App\Support\Needs\DefaultNeedTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Yolcu ihtiyaç profili (özel nitelikli kişisel veri — sağlık):
 * - need_types: acentenin ihtiyaç türleri (varsayılanlar her acenteye kopyalanır),
 * - person_needs: kişi başına tek satır; hangi ihtiyaçlar + notlar şifreli (items),
 * - persons.health_consent_at / _by: ayrı açık rıza (yoksa ihtiyaç girilemez; geri alınınca ihtiyaçlar silinir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('need_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('category', 20);
            $table->string('effect', 20)->nullable();
            $table->string('airline_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('person_needs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->unique()->constrained('persons')->cascadeOnDelete();
            $table->text('items');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->timestamp('health_consent_at')->nullable()->after('kvkk_consent_at');
            $table->foreignId('health_consent_by')->nullable()->after('health_consent_at')->constrained('users')->nullOnDelete();
        });

        // Mevcut acentelere varsayılan türler.
        $now = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('need_types')->insert(array_map(fn (array $type, int $i) => [
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'name' => $type['name'],
                'category' => $type['category']->value,
                'effect' => $type['effect']?->value,
                'airline_code' => $type['airline_code'],
                'is_active' => true,
                'sort' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ], DefaultNeedTypes::all(), array_keys(DefaultNeedTypes::all())));
        }
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_consent_by');
            $table->dropColumn('health_consent_at');
        });

        Schema::dropIfExists('person_needs');
        Schema::dropIfExists('need_types');
    }
};
