<?php

namespace App\Reports\Definitions;

use App\Models\Tour;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Collections\CollectionFilters;
use App\Support\Collections\CollectionQuery;

/**
 * Tahsilat ekranının üç sekmesinin çıktısı (ekranla aynı sorgu ve filtreler).
 */
class CollectionReport
{
    public function __construct(private readonly CollectionQuery $query) {}

    public function build(CollectionFilters $filters): Report
    {
        $data = $this->query->get($filters);
        $tourName = $filters->tourId ? Tour::query()->whereKey($filters->tourId)->value('name') : null;
        $scope = $tourName ?? 'Tüm turlar';

        if ($filters->tab === 'tahsilatlar') {
            return new Report(
                key: 'collections_payments',
                title: 'Tahsilat Raporu',
                columns: [
                    Column::date('paid_at', 'Tarih'),
                    Column::text('person', 'Yolcu', 18),
                    Column::text('tour', 'Tur', 18),
                    Column::text('type', 'İşlem', 8),
                    Column::text('method', 'Yöntem', 10),
                    Column::text('reference', 'Makbuz no', 10),
                    Column::text('received_by', 'Alan', 11),
                    Column::money('signed_amount', 'Tutar'),
                    Column::text('currency', 'Birim', 5),
                ],
                rows: $data['items']->map(fn (array $r) => [
                    ...$r,
                    'type' => $r['type'] === 'iade' ? 'İade' : 'Tahsilat',
                    'signed_amount' => $r['type'] === 'iade' ? '-'.$r['amount'] : $r['amount'],
                ])->values()->all(),
                subtitle: [$scope, $filters->from->format('d.m.Y').' – '.$filters->to->format('d.m.Y')],
                totals: $data['totals']->map(fn (array $t, string $currency) => [
                    'person' => "NET TAHSİLAT ({$t['count']} işlem)",
                    'signed_amount' => $t['net'],
                    'currency' => $currency,
                ])->values()->all(),
                landscape: true,
            );
        }

        $debtors = $filters->tab === 'borclu';

        return new Report(
            key: $debtors ? 'collections_debtors' : 'collections_completed',
            title: $debtors ? 'Borçlu Yolcular' : 'Ödemesi Tamamlanan Yolcular',
            columns: [
                Column::text('name', 'Yolcu', 18),
                Column::text('phone', 'Telefon', 11),
                Column::text('tour', 'Tur', 18),
                Column::text('group', 'Grup', 8),
                Column::money('net_price', 'Net'),
                Column::money('paid', 'Ödenen'),
                ...($debtors ? [Column::money('balance', 'Kalan'), Column::money('overdue', 'Gecikmiş')] : []),
                Column::text('currency', 'Birim', 5),
            ],
            rows: $data['items']->map(fn (array $r) => [
                'name' => $r['person']['full_name'],
                'phone' => $r['person']['phone'],
                'tour' => $r['tour']['name'],
                'group' => $r['group'],
                'net_price' => $r['net_price'],
                'paid' => $r['paid'],
                'balance' => $r['balance'],
                'overdue' => $r['overdue'],
                'currency' => $r['currency'],
            ])->values()->all(),
            subtitle: [$scope, $data['items']->count().' yolcu'],
            totals: $data['totals']->map(fn (array $t, string $currency) => [
                'name' => 'TOPLAM',
                'net_price' => $t['net_price'],
                'paid' => $t['paid'],
                'balance' => $t['balance'],
                'overdue' => $t['overdue'],
                'currency' => $currency,
            ])->values()->all(),
            landscape: true,
        );
    }
}
