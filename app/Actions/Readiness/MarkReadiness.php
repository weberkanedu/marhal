<?php

namespace App\Actions\Readiness;

use App\Enums\ReadinessStatus;
use App\Enums\RegistrationStatus;
use App\Models\ReadinessCheck;
use App\Models\ReadinessItem;
use App\Models\Registration;
use App\Models\Tour;
use App\Models\User;
use App\Support\Readiness\ReadinessBoard;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Hazırlık hücresini işaretler: tamam / sorun / boş (satır silinir). Tek tek ya da bir sütunu toplu.
 * Kurallar: madde turda takip ediliyor olmalı, kendiliğinden dolan madde (pasaport, fotoğraf)
 * elle işaretlenmez, yolcu bu turun aktif yolcusu olmalı.
 */
class MarkReadiness
{
    public function __construct(private readonly ReadinessBoard $board) {}

    public function handle(Tour $tour, Registration $registration, ReadinessItem $item, ?ReadinessStatus $status, User $by): void
    {
        $this->ensureItem($tour, $item);

        if ($registration->tour_id !== $tour->id || $registration->status === RegistrationStatus::Cancelled) {
            throw ValidationException::withMessages(['registration_id' => 'Bu yolcu bu turun aktif yolcusu değil.']);
        }

        $this->set($registration, $item, $status, $by);
    }

    /**
     * Sütunu toplu "tamam" yapar (ör. ihram setleri dağıtıldı). Dönen: değişen yolcu sayısı.
     *
     * @param  Collection<int, Registration>  $registrations
     */
    public function column(Tour $tour, ReadinessItem $item, Collection $registrations, User $by): int
    {
        $this->ensureItem($tour, $item);

        $done = ReadinessCheck::query()
            ->where('readiness_item_id', $item->id)
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->where('status', ReadinessStatus::Done)
            ->pluck('registration_id')
            ->flip();

        $changed = $registrations->reject(fn (Registration $r) => $done->has($r->id));
        $changed->each(fn (Registration $r) => $this->set($r, $item, ReadinessStatus::Done, $by));

        return $changed->count();
    }

    private function ensureItem(Tour $tour, ReadinessItem $item): void
    {
        if (! $this->board->items($tour)->contains('id', $item->id)) {
            throw ValidationException::withMessages(['item_id' => "{$item->name} bu turda takip edilmiyor."]);
        }

        if ($item->kind->isAutomatic()) {
            throw ValidationException::withMessages(['item_id' => "{$item->name} yolcu bilgisinden kendiliğinden dolar; yolcunun sayfasından düzeltin."]);
        }
    }

    private function set(Registration $registration, ReadinessItem $item, ?ReadinessStatus $status, User $by): void
    {
        $check = ReadinessCheck::query()
            ->where('registration_id', $registration->id)
            ->where('readiness_item_id', $item->id)
            ->first();

        if ($status === null) {
            $check?->delete();

            return;
        }

        ($check ?? new ReadinessCheck(['registration_id' => $registration->id, 'readiness_item_id' => $item->id]))
            ->fill(['status' => $status, 'checked_at' => now(), 'checked_by' => $by->getKey()])
            ->save();
    }
}
