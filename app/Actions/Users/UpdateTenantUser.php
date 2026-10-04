<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Kullanıcının adını, rolünü ve aktifliğini günceller.
 *
 * Kurallar: kişi kendi rolünü düşüremez / kendini pasif yapamaz; acentenin son aktif
 * yöneticisi kaldırılamaz (acente kilitlenmesin); pasif kullanıcı yeniden aktifleşirken
 * kullanıcı limiti kontrol edilir.
 */
class UpdateTenantUser
{
    /**
     * @param  array{name: string, role: string, is_active: bool}  $data
     */
    public function handle(User $actor, User $user, array $data): User
    {
        $role = UserRole::from($data['role']);
        $active = $data['is_active'];

        if ($role === UserRole::SuperAdmin) {
            throw ValidationException::withMessages(['role' => 'Geçersiz rol.']);
        }

        if ($actor->is($user) && ($role !== $user->role || ! $active)) {
            throw ValidationException::withMessages(['role' => 'Kendi rolünüzü değiştiremez veya kendinizi pasif yapamazsınız.']);
        }

        $losesAdmin = $user->role === UserRole::Admin && $user->is_active && ($role !== UserRole::Admin || ! $active);

        if ($losesAdmin && $this->activeAdminCount($user) <= 1) {
            throw ValidationException::withMessages(['role' => 'Acentenin en az bir aktif yöneticisi olmalı.']);
        }

        if (! $user->is_active && $active && ! $user->tenant?->canAddUser()) {
            throw ValidationException::withMessages(['is_active' => 'Paketinizdeki kullanıcı sınırına ulaştınız.']);
        }

        $user->update(['name' => $data['name'], 'role' => $role, 'is_active' => $active]);

        return $user;
    }

    private function activeAdminCount(User $user): int
    {
        return User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->count();
    }
}
