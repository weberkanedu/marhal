<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Paketler (10a): yıllık yolcu kotası, satışta / öne çıkan, kısa tanım, sıra. Aktif tur sınırı artık
 * kullanılmıyor (sütun sonraki bir migration'da kaldırılacak).
 *
 * Veri düzeltmesi: mevcut üç paket (Başlangıç / Profesyonel / Kurumsal) Mikat / Kafile / Kervan olur;
 * acenteler paketlerinde kalır. Yeni modüller (uçak koltuk planı, ihtiyaç kuralları) için paket satırları eklenir.
 */
return new class extends Migration
{
    /**
     * Paket tasarım sayfasındaki öneri (fiyatlar KDV hariç, yıllık = aylık × 10).
     *
     * @var array<string, array{slug: string, name: string, tagline: string, price: int, users: int, pax: int, featured: bool, sort: int, features: list<string>}>
     */
    private const PLANS = [
        'baslangic' => [
            'slug' => 'mikat', 'name' => 'Mikat', 'price' => 1490, 'users' => 3, 'pax' => 300, 'featured' => false, 'sort' => 1,
            'tagline' => 'Yeni başlayan ya da yılda birkaç tur yapan küçük acente',
            'features' => ['passengers', 'payments', 'basic_reports', 'room_planning', 'bus_planning', 'badge_generation', 'readiness', 'flight_lists'],
        ],
        'profesyonel' => [
            'slug' => 'kafile', 'name' => 'Kafile', 'price' => 3490, 'users' => 10, 'pax' => 1500, 'featured' => true, 'sort' => 2,
            'tagline' => 'Düzenli tur çıkaran, sezonu yoğun geçen acente',
            'features' => ['passengers', 'payments', 'basic_reports', 'room_planning', 'bus_planning', 'badge_generation', 'readiness', 'flight_lists',
                'flight_seats', 'need_rules', 'family_screen', 'online_signup'],
        ],
        'kurumsal' => [
            'slug' => 'kervan', 'name' => 'Kervan', 'price' => 7490, 'users' => 30, 'pax' => 5000, 'featured' => false, 'sort' => 3,
            'tagline' => 'Şubeli, entegrasyon isteyen büyük acente',
            'features' => ['passengers', 'payments', 'basic_reports', 'room_planning', 'bus_planning', 'badge_generation', 'readiness', 'flight_lists',
                'flight_seats', 'need_rules', 'family_screen', 'online_signup', 'advanced_reporting', 'api_access'],
        ],
    ];

    private const ALL_FEATURES = ['passengers', 'payments', 'basic_reports', 'room_planning', 'bus_planning', 'badge_generation', 'readiness',
        'flight_lists', 'flight_seats', 'need_rules', 'family_screen', 'online_signup', 'advanced_reporting', 'api_access'];

    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('passenger_limit')->nullable()->after('user_limit');
            $table->boolean('is_public')->default(true)->after('passenger_limit');
            $table->boolean('is_featured')->default(false)->after('is_public');
            $table->string('tagline')->nullable()->after('name');
            $table->unsignedSmallInteger('sort')->default(0)->after('is_featured');
        });

        $now = now();

        foreach (self::PLANS as $oldSlug => $plan) {
            $id = DB::table('plans')->where('slug', $oldSlug)->value('id');

            if ($id === null) {
                continue;
            }

            DB::table('plans')->where('id', $id)->update([
                'slug' => $plan['slug'],
                'name' => $plan['name'],
                'tagline' => $plan['tagline'],
                'price_monthly' => $plan['price'],
                'price_yearly' => $plan['price'] * 10,
                'currency' => 'TRY',
                'user_limit' => $plan['users'],
                'passenger_limit' => $plan['pax'],
                'is_public' => true,
                'is_featured' => $plan['featured'],
                'sort' => $plan['sort'],
                'updated_at' => $now,
            ]);

            foreach (self::ALL_FEATURES as $feature) {
                $enabled = in_array($feature, $plan['features'], true);
                $exists = DB::table('plan_features')->where('plan_id', $id)->where('feature_key', $feature)->exists();

                if ($exists) {
                    DB::table('plan_features')->where('plan_id', $id)->where('feature_key', $feature)
                        ->update(['enabled' => $enabled, 'updated_at' => $now]);
                } else {
                    DB::table('plan_features')->insert([
                        'id' => (string) Str::uuid7(), 'plan_id' => $id, 'feature_key' => $feature,
                        'enabled' => $enabled, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        // Elle açılmış başka paketler varsa yeni modüller kapalı başlar (paket ekranından açılır).
        foreach (DB::table('plans')->whereNotIn('slug', array_column(self::PLANS, 'slug'))->pluck('id') as $id) {
            foreach (['flight_seats', 'need_rules'] as $feature) {
                if (! DB::table('plan_features')->where('plan_id', $id)->where('feature_key', $feature)->exists()) {
                    DB::table('plan_features')->insert([
                        'id' => (string) Str::uuid7(), 'plan_id' => $id, 'feature_key' => $feature,
                        'enabled' => false, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            Cache::forget("tenant:{$tenantId}:features");
        }
    }

    public function down(): void
    {
        foreach (self::PLANS as $oldSlug => $plan) {
            DB::table('plans')->where('slug', $plan['slug'])->update(['slug' => $oldSlug]);
        }

        DB::table('plan_features')->whereIn('feature_key', ['flight_seats', 'need_rules'])->delete();

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['passenger_limit', 'is_public', 'is_featured', 'tagline', 'sort']);
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            Cache::forget("tenant:{$tenantId}:features");
        }
    }
};
