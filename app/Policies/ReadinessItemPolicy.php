<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ReadinessItem;
use App\Models\User;

/**
 * Hazırlık maddeleri acente geneli: yönetici ve operasyon yönetir, rehber göremez.
 */
class ReadinessItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReadinessItem $item): bool
    {
        return $this->viewAny($user) && $user->tenant_id !== null && $user->tenant_id === $item->tenant_id;
    }

    public function delete(User $user, ReadinessItem $item): bool
    {
        return $this->update($user, $item);
    }
}
