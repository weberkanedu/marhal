<?php

namespace App\Support\Dashboard;

use App\Enums\PaymentType;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Ana paneldeki aylık tahsilat grafiği: son 6 ay, kayıt para birimine çevrilmiş tutarlar
 * (iadeler düşülür). Para birimleri birbirine eklenmez; her biri ayrı seridir.
 */
class CollectionTrend
{
    private const MONTHS = 6;

    /**
     * @return array{months: list<array{key: string, label: string}>, series: array<string, list<string>>}
     */
    public function lastMonths(): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(self::MONTHS - 1);
        $labels = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

        $months = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $start->addMonths($i);
            $months[] = ['key' => $month->format('Y-m'), 'label' => $labels[$month->month - 1].' '.$month->format('y')];
        }

        $series = [];

        Payment::query()
            ->whereDate('paid_at', '>=', $start)
            ->with(['registration' => fn ($q) => $q->withTrashed()->select(['id', 'currency'])])
            ->get(['id', 'registration_id', 'type', 'amount_in_registration_currency', 'paid_at'])
            ->each(function (Payment $payment) use (&$series, $months): void {
                $currency = $payment->registration->currency;
                $series[$currency] ??= array_fill(0, count($months), '0.00');
                $index = array_search($payment->paid_at->format('Y-m'), array_column($months, 'key'), true);

                if ($index === false) {
                    return;
                }

                $series[$currency][$index] = $payment->type === PaymentType::Refund
                    ? Money::sub($series[$currency][$index], $payment->amount_in_registration_currency)
                    : Money::add($series[$currency][$index], $payment->amount_in_registration_currency);
            });

        // En çok tahsilat yapılan para birimi önce.
        uasort($series, fn (array $a, array $b) => (float) array_sum($b) <=> (float) array_sum($a));

        return ['months' => $months, 'series' => $series];
    }
}
