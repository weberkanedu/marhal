<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VehicleType;

/**
 * Araç tipleri acente geneli: yönetici ve operasyon yönetir, rehber göremez.
 */
class VehicleTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, VehicleType $type): bool
    {
        return $this->viewAny($user) && $user->tenant_id !== null && $user->tenant_id === $type->tenant_id;
    }

    public function delete(User $user, VehicleType $type): bool
    {
        return $this->update($user, $type);
    }
}
