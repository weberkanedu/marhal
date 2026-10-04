<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Registration;

/**
 * Kayıt durumuna göre iptal alanlarını tutarlı hale getirir:
 * iptalde tarih atanır (ilk iptal tarihi korunur), iptal kaldırılınca alanlar temizlenir.
 */
class ApplyRegistrationStatus
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data, ?Registration $current = null): array
    {
        $data['discount'] ??= 0;

        if (RegistrationStatus::from($data['status']) === RegistrationStatus::Cancelled) {
            $data['cancelled_at'] = $current->cancelled_at ?? now();
        } else {
            $data['cancelled_at'] = null;
            $data['cancel_reason'] = null;
        }

        return $data;
    }
}
