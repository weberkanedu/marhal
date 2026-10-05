<?php

namespace App\Actions\Buses;

use App\Enums\RegistrationStatus;
use App\Models\Bus;
use App\Models\Registration;
use App\Models\SeatAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Yolcuyu koltuğa oturtur. Engelleyen kurallar: başka turun / iptal edilmiş kaydı, olmayan koltuk,
 * rehbere ayrılmış koltuk, dolu koltuk. Yolcu turda başka bir koltuktaysa (başka otobüs dahil)
 * yeni koltuğa taşınır. Yan koltukta karşı cinsten yolcu olması engellemez, sadece uyarıdır.
 * `$swap`: hedef koltuk doluysa ve yolcu aynı otobüste oturuyorsa iki yolcu yer değiştirir (sürükle-bırak).
 */
class AssignSeat
{
    public function handle(Bus $bus, Registration $registration, int $seatNo, bool $swap = false): SeatAssignment
    {
        return DB::transaction(function () use ($bus, $registration, $seatNo, $swap): SeatAssignment {
            // Aynı koltuğa aynı anda iki yerleştirme yapılmasın.
            $bus = Bus::query()->lockForUpdate()->whereKey($bus->getKey())->firstOrFail();
            $name = $registration->person->full_name;

            if ($registration->tour_id !== $bus->tour_id || $registration->status === RegistrationStatus::Cancelled) {
                $this->fail("{$name} bu turun aktif yolcusu değil.");
            }

            if (! $bus->layout()->has($seatNo)) {
                $this->fail("{$bus->name} otobüsünde {$seatNo} numaralı koltuk yok.");
            }

            if (in_array($seatNo, $bus->reserved(), true)) {
                $this->fail("{$seatNo} numaralı koltuk rehber / görevli için ayrılmış.");
            }

            $taken = $bus->seats()->where('seat_no', $seatNo)->where('registration_id', '!=', $registration->getKey())->with('registration.person')->first();

            $current = $bus->seats()->where('registration_id', $registration->getKey())->first();

            if ($taken !== null && $swap && $current !== null) {
                // Yer değiştirme: (otobüs, koltuk) benzersiz olduğundan önce karşıdaki kayıt kaldırılır.
                $other = $taken->registration_id;
                $oldSeat = $current->seat_no;
                $taken->delete();
                $current->update(['seat_no' => $seatNo]);
                (new SeatAssignment)->forceFill([
                    'tenant_id' => $bus->tenant_id, 'tour_id' => $bus->tour_id, 'bus_id' => $bus->getKey(),
                    'registration_id' => $other, 'seat_no' => $oldSeat,
                ])->save();

                return $current;
            }

            if ($taken !== null) {
                $this->fail("{$seatNo} numaralı koltukta {$taken->registration->person->full_name} oturuyor.");
            }

            return SeatAssignment::query()->updateOrCreate(
                ['tour_id' => $bus->tour_id, 'registration_id' => $registration->getKey()],
                ['bus_id' => $bus->getKey(), 'seat_no' => $seatNo],
            );
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['seat' => $message]);
    }
}
