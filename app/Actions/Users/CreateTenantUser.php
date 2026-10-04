<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Acenteye kullanıcı ekler. E-posta altyapısı olmadan çalışabilmesi için tek seferlik
 * geçici şifre üretir; kullanıcı ilk girişte şifresini değiştirmek zorundadır.
 *
 * Kurallar: paketin kullanıcı limiti aşılamaz; platform yöneticisi rolü verilemez.
 */
class CreateTenantUser
{
    /**
     * @return array{user: User, password: string}
     */
    public function handle(Tenant $tenant, string $name, string $email, UserRole $role, bool $enforceLimit = true): array
    {
        if ($role === UserRole::SuperAdmin) {
            throw ValidationException::withMessages(['role' => 'Acente kullanıcısına platform yöneticisi rolü verilemez.']);
        }

        if ($enforceLimit && ! $tenant->canAddUser()) {
            throw ValidationException::withMessages([
                'email' => "Paketinizdeki kullanıcı sınırına ({$tenant->plan->user_limit}) ulaştınız. Kullanmadığınız bir hesabı pasif yaparak yer açabilir veya paketinizi yükseltebilirsiniz.",
            ]);
        }

        $password = self::temporaryPassword();

        $user = new User([
            'name' => $name,
            'email' => Str::lower($email),
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);
        $user->forceFill([
            'tenant_id' => $tenant->getKey(),
            'email_verified_at' => now(),
            'must_change_password' => true,
        ])->save();

        return ['user' => $user, 'password' => $password];
    }

    /**
     * Okunması kolay, karışan karakterler (0/O, 1/l/I) içermeyen geçici şifre.
     */
    public static function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
