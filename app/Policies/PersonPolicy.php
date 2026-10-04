<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function view(User $user, Person $person): bool
    {
        return $this->viewAny($user) && $this->sameTenant($user, $person);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Operations);
    }

    public function update(User $user, Person $person): bool
    {
        return $this->create($user) && $this->sameTenant($user, $person);
    }

    public function delete(User $user, Person $person): bool
    {
        return $this->create($user) && $this->sameTenant($user, $person);
    }

    /**
     * Kimlik / pasaport numarasını maskesiz görme (SPEC.md §5).
     */
    public function revealSensitive(User $user, Person $person): bool
    {
        return $user->role->canRevealSensitiveData() && $this->sameTenant($user, $person);
    }

    /**
     * Kapsam hatasına karşı ikinci kontrol.
     */
    private function sameTenant(User $user, Person $person): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $person->tenant_id;
    }
}
