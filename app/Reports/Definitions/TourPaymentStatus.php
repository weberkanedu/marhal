<?php

namespace App\Reports\Definitions;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\Tour;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Money;
use App\Support\TurkishText;

/**
 * Turun yolcu bazında ödeme durumu: net ücret, ödenen, kalan, gecikmiş + toplam satırı.
 */
class TourPaymentStatus
{
    public function build(Tour $tour): Report
    {
        $registrations = $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->with(['person:id,first_name,last_name,phone', 'group:id,name'])
            ->withPaidTotal()
            ->withDueTotal()
            ->get()
            ->sort(fn (Registration $a, Registration $b) => TurkishText::compare(
                $a->person->last_name.' '.$a->person->first_name,
                $b->person->last_name.' '.$b->person->first_name,
            ))
            ->values();

        $rows = $registrations->map(fn (Registration $r) => [
            'name' => $r->person->full_name,
            'phone' => $r->person->phone,
            'group' => $r->group?->name,
            'status' => $r->status->label(),
            'net_price' => $r->netPrice(),
            'paid' => $r->paidTotal(),
            'balance' => $r->balance(),
            'overdue' => $r->overdue(),
        ])->all();

        $sum = fn (string $key) => array_reduce($rows, fn (string $c, array $r) => Money::add($c, $r[$key]), '0.00');

        return new Report(
            key: 'tour_payments',
            title: "{$tour->name} Ödeme Durumu",
            columns: [
                Column::text('name', 'Yolcu', 22),
                Column::text('phone', 'Telefon', 12),
                Column::text('group', 'Grup', 9),
                Column::text('status', 'Durum', 9),
                Column::money('net_price', "Net ({$tour->currency})"),
                Column::money('paid', "Ödenen ({$tour->currency})"),
                Column::money('balance', "Kalan ({$tour->currency})"),
                Column::money('overdue', "Gecikmiş ({$tour->currency})"),
            ],
            rows: $rows,
            subtitle: [
                $tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y'),
                count($rows).' kayıt (iptaller hariç)',
            ],
            totals: [[
                'name' => 'TOPLAM',
                'net_price' => $sum('net_price'),
                'paid' => $sum('paid'),
                'balance' => $sum('balance'),
                'overdue' => $sum('overdue'),
            ]],
        );
    }
}
