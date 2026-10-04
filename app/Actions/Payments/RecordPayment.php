<?php

namespace App\Actions\Payments;

use App\Enums\PaymentType;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bir kayda tahsilat veya iade işler.
 *
 * Kurallar:
 * - Ödeme kayıt para biriminden farklıysa kur zorunludur (kayıt para birimine çeviri için).
 * - İptal edilmiş kayda tahsilat alınmaz (iade yapılabilir).
 * - İade, o ana kadar ödenen tutarı aşamaz.
 */
class RecordPayment
{
    /**
     * @param  array<string, mixed>  $data  type, amount, currency, exchange_rate?, method, paid_at, reference?, notes?
     */
    public function handle(Registration $registration, array $data, ?User $receivedBy = null): Payment
    {
        return DB::transaction(function () use ($registration, $data, $receivedBy): Payment {
            $registration = Registration::query()->whereKey($registration->getKey())->lockForUpdate()->firstOrFail();
            $type = PaymentType::from($data['type']);
            $sameCurrency = $data['currency'] === $registration->currency;

            if (! $sameCurrency && empty($data['exchange_rate'])) {
                throw ValidationException::withMessages([
                    'exchange_rate' => "Ödeme {$data['currency']} ile yapılıyor; {$registration->currency} karşılığı için kur giriniz.",
                ]);
            }

            if ($type === PaymentType::Collection && $registration->status === RegistrationStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'amount' => 'İptal edilmiş kayda tahsilat alınamaz. İade yapabilirsiniz.',
                ]);
            }

            $payment = new Payment([
                ...$data,
                'exchange_rate' => $sameCurrency ? 1 : $data['exchange_rate'],
                'received_by' => $receivedBy?->getKey(),
            ]);
            $payment->registration()->associate($registration);

            if ($type === PaymentType::Refund) {
                $refundInRegistrationCurrency = Money::mul($payment->amount, $payment->exchange_rate);

                if (bccomp($refundInRegistrationCurrency, $registration->paidTotal(), 2) > 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'İade tutarı ödenen toplamı ('.$registration->paidTotal().' '.$registration->currency.') aşamaz.',
                    ]);
                }
            }

            $payment->tenant_id = $registration->tenant_id;
            $payment->save();

            return $payment;
        });
    }
}
