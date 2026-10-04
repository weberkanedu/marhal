<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\User;

/**
 * Turlar ve turun içindeki gruplar / kayıtlar bu politikayla yetkilendirilir.
 *
 * Rehber: yalnızca rehberi olduğu grubun bulunduğu turları görür; o turda da sadece
 * kendi grubunun yolcularını, para ve kimlik bilgisi olmadan (TourController guide modu).
 */
class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations, UserRole::Guide);
    }

    public function view(User $user, Tour $tour): bool
    {
        if (! $this->sameTenant($user, $tour)) {
            return false;
        }

        if ($user->hasRole(UserRole::Guide)) {
            return $tour->groups()->where('guide_user_id', $user->getKey())->exists();
        }

        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    /**
     * Tüm turların finans ekranlarını görme (tahsilat ekranı, tahsilat raporları).
     */
    public function viewAnyFinance(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    /**
     * Ücret, ödeme, bakiye bilgilerini görme (rehber göremez).
     */
    public function viewFinance(User $user, Tour $tour): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations) && $this->sameTenant($user, $tour);
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

    /**
     * Tahsilat / iade girme, taksit planı düzenleme.
     */
    public function managePayments(User $user, Tour $tour): bool
    {
        return $user->role->canManagePayments() && $this->sameTenant($user, $tour);
    }

    /**
     * Girilmiş bir ödemeyi silme (hatalı kayıt düzeltme) — sadece yönetici.
     */
    public function deletePayments(User $user, Tour $tour): bool
    {
        return $user->hasRole(UserRole::Admin) && $this->sameTenant($user, $tour);
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
