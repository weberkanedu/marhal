<?php

namespace App\Actions\Registrations;

use App\Models\Registration;
use Illuminate\Validation\ValidationException;

/**
 * Yanlışlıkla eklenen kaydı turdan tamamen çıkarır. Ödemesi olan kayıt silinmez, iptal edilmelidir.
 */
class RemoveRegistration
{
    public function handle(Registration $registration): void
    {
        if ($registration->payments()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'registration' => 'Ödemesi olan kayıt silinemez; durumunu "İptal" yapın.',
            ]);
        }

        $registration->installments()->delete();
        $registration->forceDelete();
    }
}
