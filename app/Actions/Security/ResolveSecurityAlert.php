<?php

namespace App\Actions\Security;

use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Platform yöneticisi "Kullanıcıyı doğrula" der: kullanıcının bütün oturumları ve kayıtlı cihazları sıfırlanır
 * ("Beni hatırla" dahil), açık uyarıları kapanır. Kullanıcı şifresiyle yeniden girer; yeni cihazlar baştan sayılır.
 * E-posta servisi bağlanınca (10d-2) kullanıcıya yeni cihaz doğrulaması da gönderilecek.
 */
class ResolveSecurityAlert
{
    public function handle(SecurityAlert $alert, User $actor): void
    {
        $user = $alert->user;

        DB::transaction(function () use ($user, $actor): void {
            $user->devices()->delete();
            $user->forceFill(['current_device_id' => null, 'remember_token' => null])->saveQuietly();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            SecurityAlert::query()->where('user_id', $user->id)->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $actor->id]);
        });
    }
}
