<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class UpdateRegistration
{
    public function __construct(
        private readonly ApplyRegistrationStatus $applyStatus,
        private readonly RegisterPerson $registerPerson,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Registration $registration, array $data): Registration
    {
        return DB::transaction(function () use ($registration, $data): Registration {
            $newStatus = RegistrationStatus::from($data['status']);

            // İptal edilmiş bir kayıt yeniden açılıyorsa kapasiteye tekrar girer.
            if ($registration->status === RegistrationStatus::Cancelled && $newStatus !== RegistrationStatus::Cancelled) {
                $this->registerPerson->ensureCapacity($registration->tour, $newStatus);
            }

            unset($data['person_id']);
            $registration->update($this->applyStatus->handle($data, $registration));

            // İptal edilen yolcunun oda, koltuk ve uçuş kayıtları kaldırılır (başkasına verilebilsin,
            // havayolu listesinde görünmesin).
            if ($newStatus === RegistrationStatus::Cancelled) {
                $registration->roomAssignments()->delete();
                $registration->seatAssignments()->delete();
                $registration->flightPassengers()->delete();
            }

            return $registration;
        });
    }
}
