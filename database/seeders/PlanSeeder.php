<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * SPEC.md §3 başlangıç paket matrisi. Tekrar çalıştırılabilir (production'da da güvenli).
 * Fiyatlar örnektir; platform panelinden güncellenecek.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $base = [Feature::Passengers, Feature::Payments, Feature::BasicReports, Feature::Readiness, Feature::FamilyScreen];
        $pro = [...$base, Feature::RoomPlanning, Feature::BusPlanning, Feature::FlightLists];
        $enterprise = [...$pro, Feature::BadgeGeneration, Feature::AdvancedReporting, Feature::ApiAccess];

        $plans = [
            ['slug' => 'baslangic', 'name' => 'Başlangıç', 'user_limit' => 1, 'active_tour_limit' => 1, 'features' => $base],
            ['slug' => 'profesyonel', 'name' => 'Profesyonel', 'user_limit' => 5, 'active_tour_limit' => 5, 'features' => $pro],
            ['slug' => 'kurumsal', 'name' => 'Kurumsal', 'user_limit' => null, 'active_tour_limit' => null, 'features' => $enterprise],
        ];

        foreach ($plans as $data) {
            $plan = Plan::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'user_limit' => $data['user_limit'],
                'active_tour_limit' => $data['active_tour_limit'],
                'currency' => 'TRY',
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
