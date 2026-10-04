<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\User;

/**
 * Turlar ve turun içindeki gruplar / kayıtlar bu politikayla yetkilendirilir.
 * Rehberin kendi grubunu görmesi 5. adımda (rehber ekranı) eklenecek.
 */
class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function view(User $user, Tour $tour): bool
    {
        return $this->viewAny($user) && $this->sameTenant($user, $tour);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    /**
     * Tur bilgileri, grupları ve kayıtlarını değiştirme.
     */
    public function update(User $user, Tour $tour): bool
    {
        return $this->create($user) && $this->sameTenant($user, $tour);
    }

    public function delete(User $user, Tour $tour): bool
    {
        return $user->hasRole(UserRole::Admin) && $this->sameTenant($user, $tour);
    }

    private function sameTenant(User $user, Tour $tour): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $tour->tenant_id;
    }
}
