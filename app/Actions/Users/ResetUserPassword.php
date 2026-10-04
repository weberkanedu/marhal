<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Yöneticinin bir kullanıcıya yeni geçici şifre vermesi (şifresini unutan personel için).
 * Kullanıcının açık oturumları kapatılır, ilk girişte şifresini değiştirmesi istenir.
 */
class ResetUserPassword
{
    public function handle(User $user): string
    {
        $password = CreateTenantUser::temporaryPassword();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();

        // Veritabanı oturumlarını kapat (session driver = database).
        DB::table('sessions')->where('user_id', $user->getKey())->delete();

        return $password;
    }
}
