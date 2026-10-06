<?php

namespace App\Actions\Family;

use App\Enums\RegistrationStatus;
use App\Models\FamilyLink;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Yolcunun ailesi için aile ekranı linki üretir. Kural: yolcunun izni alınmış olmalı (personel işaretler,
 * tarih ve kim kaydedilir); iptal edilmiş kayda link verilmez; yolcunun eski linki iptal edilir
 * (aynı anda tek etkin link). Link turun bitişinden 7 gün sonra kendiliğinden kapanır.
 */
class CreateFamilyLink
{
    public const DAYS_AFTER_TOUR = 7;

    public function handle(Registration $registration, bool $consent, User $by): FamilyLink
    {
        if (! $consent) {
            throw ValidationException::withMessages(['consent' => 'Linki oluşturmak için yolcunun iznini işaretleyin.']);
        }

        if ($registration->status === RegistrationStatus::Cancelled) {
            throw ValidationException::withMessages(['consent' => 'İptal edilmiş kayda aile linki verilmez.']);
        }

        return DB::transaction(function () use ($registration, $by): FamilyLink {
            $registration->familyLinks()->active()->get()->each(fn (FamilyLink $l) => $l->update(['revoked_at' => now()]));

            $token = Str::random(24);

            return $registration->familyLinks()->create([
                'token' => $token,
                'token_hash' => FamilyLink::hash($token),
                'consent_at' => now(),
                'consent_by' => $by->getKey(),
                'expires_at' => $registration->tour->end_date->addDays(self::DAYS_AFTER_TOUR)->endOfDay(),
            ]);
        });
    }
}
