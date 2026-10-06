<?php

namespace App\Actions\Signup;

use App\Enums\SignupStatus;
use App\Models\NeedType;
use App\Models\SignupLink;
use App\Models\SignupRequest;
use Illuminate\Validation\ValidationException;

/**
 * Yolcunun telefondan gönderdiği ön kayıt başvurusunu kaydeder (personel onayına düşer).
 * Kurallar: KVKK aydınlatma onayı zorunlu; ihtiyaç seçildiyse sağlık verisi için ayrı açık rıza zorunlu;
 * yalnız acentenin açık ihtiyaç türleri; aynı turda aynı pasaportla bekleyen / onaylanmış başvuru varsa tekrar alınmaz.
 */
class SubmitSignupRequest
{
    /**
     * @param  array{first_name: string, last_name: string, gender: string, birth_date?: string|null, nationality: string, passport_no?: string|null, passport_expiry_date?: string|null, phone: string, email?: string|null}  $person
     * @param  list<string>  $needs
     */
    public function handle(SignupLink $link, array $person, array $needs, bool $kvkk, bool $healthConsent, bool $readFromPassport): SignupRequest
    {
        if (! $kvkk) {
            throw ValidationException::withMessages(['kvkk' => 'Devam etmek için aydınlatma metnini onaylayın.']);
        }

        $needs = array_values(array_unique($needs));

        if ($needs !== [] && ! $healthConsent) {
            throw ValidationException::withMessages(['health_consent' => 'İhtiyaç bilgisi sağlık verisidir; paylaşmak için ayrıca onay verin.']);
        }

        if (count($needs) !== NeedType::query()->where('is_active', true)->whereIn('id', $needs)->count()) {
            throw ValidationException::withMessages(['needs' => 'Geçersiz ihtiyaç seçimi.']);
        }

        $passport = isset($person['passport_no']) ? strtoupper(preg_replace('/\s+/', '', $person['passport_no']) ?? '') : '';

        if ($passport !== '') {
            $duplicate = SignupRequest::query()
                ->where('tour_id', $link->tour_id)
                ->whereIn('status', [SignupStatus::Pending, SignupStatus::Approved])
                ->get()
                ->contains(fn (SignupRequest $r) => ($r->data['passport_no'] ?? null) === $passport);

            if ($duplicate) {
                throw ValidationException::withMessages(['passport_no' => 'Bu pasaportla bu tura başvuru zaten alınmış. Acentenizle görüşün.']);
            }
        }

        return SignupRequest::query()->create([
            'tour_id' => $link->tour_id,
            'signup_link_id' => $link->id,
            'data' => [
                'first_name' => trim($person['first_name']),
                'last_name' => trim($person['last_name']),
                'gender' => $person['gender'],
                'birth_date' => $person['birth_date'] ?? null,
                'nationality' => strtoupper($person['nationality']),
                'passport_no' => $passport !== '' ? $passport : null,
                'passport_expiry_date' => $person['passport_expiry_date'] ?? null,
                'phone' => trim($person['phone']),
                'email' => $person['email'] ?? null,
            ],
            'needs' => $needs === [] ? null : $needs,
            'read_from_passport' => $readFromPassport,
            'kvkk_consent_at' => now(),
            'health_consent_at' => $needs !== [] ? now() : null,
        ]);
    }
}
