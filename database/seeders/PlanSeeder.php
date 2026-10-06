<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Paket tasarım sayfasındaki öneri: Mikat · Kafile · Kervan (fiyatlar KDV hariç, yıllık = aylık × 10).
 * Tekrar çalıştırılabilir; fiyat ve limitler sonra platform panelinden değiştirilir.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $mikat = [Feature::Passengers, Feature::Payments, Feature::BasicReports, Feature::RoomPlanning, Feature::BusPlanning,
            Feature::BadgeGeneration, Feature::Readiness, Feature::FlightLists];
        $kafile = [...$mikat, Feature::FlightSeats, Feature::NeedRules, Feature::FamilyScreen, Feature::OnlineSignup];
        $kervan = [...$kafile, Feature::AdvancedReporting, Feature::ApiAccess];

        $plans = [
            ['slug' => 'mikat', 'name' => 'Mikat', 'tagline' => 'Yeni başlayan ya da yılda birkaç tur yapan küçük acente',
                'price' => 1490, 'users' => 3, 'pax' => 300, 'featured' => false, 'features' => $mikat],
            ['slug' => 'kafile', 'name' => 'Kafile', 'tagline' => 'Düzenli tur çıkaran, sezonu yoğun geçen acente',
                'price' => 3490, 'users' => 10, 'pax' => 1500, 'featured' => true, 'features' => $kafile],
            ['slug' => 'kervan', 'name' => 'Kervan', 'tagline' => 'Şubeli, entegrasyon isteyen büyük acente',
                'price' => 7490, 'users' => 30, 'pax' => 5000, 'featured' => false, 'features' => $kervan],
        ];

        foreach ($plans as $i => $data) {
            $plan = Plan::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'tagline' => $data['tagline'],
                'price_monthly' => $data['price'],
                'price_yearly' => $data['price'] * Plan::YEARLY_MONTHS,
                'currency' => 'TRY',
                'user_limit' => $data['users'],
                'passenger_limit' => $data['pax'],
                'is_featured' => $data['featured'],
                'sort' => $i + 1,
            ]);

            foreach (Feature::cases() as $feature) {
                $plan->features()->updateOrCreate(
                    ['feature_key' => $feature->value],
                    ['enabled' => in_array($feature, $data['features'], true)],
                );
            }
        }
    }
}
