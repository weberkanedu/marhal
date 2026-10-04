<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bir kişiyi tura kaydeder. Ekran (RegistrationController) ve ileride API aynı kuralı kullanır.
 *
 * Kurallar: kayıt para birimi turun para birimidir; iptal edilmemiş kayıtlar kapasiteyi doldurur.
 */
class RegisterPerson
{
    public function __construct(private readonly ApplyRegistrationStatus $applyStatus) {}

    /**
     * @param  array<string, mixed>  $data  person_id, group_id?, room_type?, price, discount?, status, cancel_reason?, notes?
     */
    public function handle(Tour $tour, array $data): Registration
    {
        return DB::transaction(function () use ($tour, $data): Registration {
            // Aynı anda iki kayıt kapasiteyi aşmasın diye tur satırı kilitlenir.
            $tour = Tour::query()->whereKey($tour->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureCapacity($tour, RegistrationStatus::from($data['status']));

            $registration = new Registration([
                ...$this->applyStatus->handle($data),
                'currency' => $tour->currency,
                'registered_at' => now(),
            ]);
            // Acente bağlamı olmayan çağrılarda (API, komut) da doğru acenteye yazılsın.
            $registration->tenant_id = $tour->tenant_id;
            $tour->registrations()->save($registration);

            return $registration;
        });
    }

    public function ensureCapacity(Tour $tour, RegistrationStatus $status): void
    {
        if ($tour->capacity === null || $status === RegistrationStatus::Cancelled) {
            return;
        }

        $occupied = $tour->registrations()->where('status', '!=', RegistrationStatus::Cancelled)->count();

        if ($occupied >= $tour->capacity) {
            throw ValidationException::withMessages([
                'person_id' => "Tur kapasitesi dolu ({$tour->capacity} kişi).",
            ]);
        }
    }
}
