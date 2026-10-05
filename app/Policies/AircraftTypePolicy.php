<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AircraftType;
use App\Models\User;

/**
 * Uçak tipleri acente geneli: yönetici ve operasyon yönetir, rehber göremez.
 */
class AircraftTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AircraftType $type): bool
    {
        return $this->viewAny($user) && $user->tenant_id !== null && $user->tenant_id === $type->tenant_id;
    }

    public function delete(User $user, AircraftType $type): bool
    {
        return $this->update($user, $type);
    }
}
