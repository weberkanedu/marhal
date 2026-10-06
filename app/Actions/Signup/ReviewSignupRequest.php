<?php

namespace App\Actions\Signup;

use App\Actions\Persons\SavePersonNeeds;
use App\Actions\Registrations\RegisterPerson;
use App\Enums\RegistrationStatus;
use App\Enums\SignupStatus;
use App\Models\Person;
use App\Models\Registration;
use App\Models\SignupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ön kayıt başvurusunu onaylar ya da reddeder.
 * Onay: pasaport numarasıyla kayıtlı kişi varsa o kullanılır (bilgileri ezilmez), yoksa kişi oluşturulur;
 * kişi turda yoksa "Ön kayıt" olarak eklenir (kapasite kuralı RegisterPerson'da). İhtiyaçlar, başvurudaki
 * sağlık rızasıyla yolcunun profiline yazılır. Onay / redden sonra başvurudaki kimlik bilgileri silinir
 * (yalnız ad soyad kalır; kişi kaydı artık asıl kaynak).
 */
class ReviewSignupRequest
{
    public function __construct(
        private readonly RegisterPerson $register,
        private readonly SavePersonNeeds $needs,
    ) {}

    public function approve(SignupRequest $request, User $by): Registration
    {
        $this->ensurePending($request);

        return DB::transaction(function () use ($request, $by): Registration {
            $data = $request->data;
            $tour = $request->tour;

            $person = filled($data['passport_no'] ?? null)
                ? Person::query()->wherePassportNo((string) $data['passport_no'])->first()
                : null;

            if ($person === null) {
                $person = new Person([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'nationality' => $data['nationality'],
                    'passport_no' => $data['passport_no'] ?? null,
                    'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                ]);
                $person->kvkk_consent_at = $request->kvkk_consent_at;
                $person->tenant_id = $request->tenant_id;
                $person->save();
            }

            $registration = Registration::query()->where('tour_id', $tour->id)->where('person_id', $person->id)->first()
                ?? $this->register->handle($tour, [
                    'person_id' => $person->id,
                    'status' => RegistrationStatus::Pending->value,
                    'price' => $tour->default_price ?? 0,
                    'notes' => 'Telefonla ön kayıttan',
                ]);

            if ($request->needs && $request->health_consent_at !== null) {
                $this->needs->grantConsent($person, $by);
                $this->needs->save($person->refresh(), array_map(fn (string $id) => ['type_id' => $id], $request->needs), $by);
            }

            $this->close($request, SignupStatus::Approved, $by, $person->id, $registration->id);

            return $registration;
        });
    }

    public function reject(SignupRequest $request, User $by): void
    {
        $this->ensurePending($request);
        $this->close($request, SignupStatus::Rejected, $by);
    }

    private function ensurePending(SignupRequest $request): void
    {
        if ($request->status !== SignupStatus::Pending) {
            throw ValidationException::withMessages(['request' => 'Bu başvuru zaten '.mb_strtolower($request->status->label(), 'UTF-8').'.']);
        }
    }

    private function close(SignupRequest $request, SignupStatus $status, User $by, ?string $personId = null, ?string $registrationId = null): void
    {
        $request->forceFill([
            'status' => $status,
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => now(),
            'person_id' => $personId,
            'registration_id' => $registrationId,
            // Kimlik bilgileri başvuruda tutulmaz (veri en aza indirme); ad soyad listede görünsün diye kalır.
            'data' => ['first_name' => $request->data['first_name'], 'last_name' => $request->data['last_name']],
            'needs' => null,
        ])->save();
    }
}
