<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\NeedType;
use App\Models\User;

/**
 * İhtiyaç türleri acente geneli: yönetici ve operasyon yönetir, rehber göremez.
 */
class NeedTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, NeedType $type): bool
    {
        return $this->viewAny($user) && $user->tenant_id !== null && $user->tenant_id === $type->tenant_id;
    }

    public function delete(User $user, NeedType $type): bool
    {
        return $this->update($user, $type);
    }
}
