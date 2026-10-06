<?php

namespace App\Actions\Plans;

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Platform yöneticisinin paket değişikliği: fiyat, limitler, satışta mı ve paketteki modüller.
 *
 * Kurallar: yıllık fiyat = aylık × 10 (2 ay bizden); en az bir paket satışta kalır; değişiklik o paketi
 * kullanan bütün acentelere hemen yansır (FeatureGate önbelleği temizlenir). Satıştan kaldırılan pakette
 * mevcut acenteler kalır.
 */
class UpdatePlan
{
    /**
     * @param  array{price_monthly: int|float|string, user_limit: int|null, passenger_limit: int|null, is_public: bool, features: list<string>}  $data
     */
    public function handle(Plan $plan, array $data): Plan
    {
        if ($plan->is_public && ! $data['is_public'] && ! Plan::query()->whereKeyNot($plan->getKey())->where('is_public', true)->exists()) {
            throw ValidationException::withMessages(['is_public' => 'En az bir paket satışta kalmalı.']);
        }

        DB::transaction(function () use ($plan, $data): void {
            $plan->update([
                'price_monthly' => $data['price_monthly'],
                'price_yearly' => (float) $data['price_monthly'] * Plan::YEARLY_MONTHS,
                'user_limit' => $data['user_limit'],
                'passenger_limit' => $data['passenger_limit'],
                'is_public' => $data['is_public'],
            ]);

            foreach (Feature::cases() as $feature) {
                PlanFeature::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $feature->value],
                    ['enabled' => in_array($feature->value, $data['features'], true)],
                );
            }
        });

        $plan->flushTenantFeatureCache();

        return $plan;
    }
}
