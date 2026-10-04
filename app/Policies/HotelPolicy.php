<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\User;

/**
 * Otel listesi acente geneli: yönetici ve operasyon yönetir, rehber göremez.
 */
class HotelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Hotel $hotel): bool
    {
        return $this->viewAny($user) && $user->tenant_id !== null && $user->tenant_id === $hotel->tenant_id;
    }

    public function delete(User $user, Hotel $hotel): bool
    {
        return $this->update($user, $hotel);
    }
}
