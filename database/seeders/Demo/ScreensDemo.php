<?php

namespace Database\Seeders\Demo;

use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\RegistrationStatus;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Support\Money;

/**
 * Tasarım yenileme 2 (ekranlar) örnek verisi: turun WhatsApp grup bağlantısı, taksit planı olmayan
 * borçlulara bu ay + gelecek ay taksit (Tahsilat özetinde "bu ay vadesi gelen" dolsun) ve iki yolcunun
 * pasaportu süzgeçte görünsün diye 6 aydan kısa.
 */
class ScreensDemo
{
    public function __construct(private readonly ReplaceInstallmentPlan $installments) {}

    public function run(Tenant $tenant): string
    {
        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'Aktif tur yok; ekran örnekleri eklenmedi.';
        }

        $tour->update(['whatsapp_link' => $tour->whatsapp_link ?? 'https://chat.whatsapp.com/MarhalDemoGrubu']);

        $plans = 0;
        $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereDoesntHave('installments')
            ->withPaidTotal()
            ->get()
            ->filter(fn (Registration $r) => bccomp($r->balance(), '0', 2) > 0)
            ->each(function (Registration $r) use (&$plans): void {
                $half = Money::of(bcdiv($r->balance(), '2', 2));
                $this->installments->handle($r, [
                    ['due_date' => now()->endOfMonth()->toDateString(), 'amount' => $half],
                    ['due_date' => now()->addMonthNoOverflow()->endOfMonth()->toDateString(), 'amount' => Money::sub($r->balance(), $half)],
                ]);
                $plans++;
            });

        $expiring = Person::query()
            ->whereHas('registrations', fn ($q) => $q->where('tour_id', $tour->id))
            ->orderBy('last_name')
            ->limit(2)
            ->get()
            ->each(fn (Person $p) => $p->update(['passport_expiry_date' => now()->addMonths(4)->toDateString()]))
            ->count();

        return "WhatsApp bağlantısı, {$plans} taksit planı, {$expiring} kısa süreli pasaport eklendi.";
    }
}
