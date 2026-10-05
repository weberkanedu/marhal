<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Yaka kartı ayarı acente geneli: yönetici ve operasyon değiştirir, rehber göremez.
 */
class BadgeSettingPolicy
{
    public function update(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRole(UserRole::Admin, UserRole::Operations);
    }
}
