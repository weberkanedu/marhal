<?php

namespace App\Support\Collections;

use App\Enums\PaymentType;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Borçlu / tamamlanan kayıtlar ve tahsilat hareketleri. Tahsilat ekranı ve
 * Excel/PDF raporları aynı sorguyu kullanır (ekranda görülen = raporda çıkan).
 */
class CollectionQuery
{
    /**
     * @return array{items: Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    public function get(CollectionFilters $filters): array
    {
        return $filters->tab === 'tahsilatlar'
            ? $this->payments($filters)
            : $this->registrations($filters->tourId, $filters->tab === 'borclu');
    }

    /**
     * @return array{items: Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    public function registrations(?string $tourId, bool $debtors): array
    {
        $items = Registration::query()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->when($tourId, fn ($q) => $q->where('tour_id', $tourId))
            ->with(['person:id,first_name,last_name,phone', 'tour:id,name,start_date', 'group:id,name'])
            ->withPaidTotal()
            ->withDueTotal()
            ->get()
            ->filter(fn (Registration $r) => $debtors
                ? bccomp($r->balance(), '0', 2) > 0
                : bccomp($r->balance(), '0', 2) <= 0)
            ->sortBy([
                fn (Registration $a, Registration $b) => bccomp($b->overdue(), $a->overdue(), 2),
                fn (Registration $a, Registration $b) => bccomp($b->balance(), $a->balance(), 2),
            ])
            ->values()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'person' => ['id' => $r->person->id, 'full_name' => $r->person->full_name, 'phone' => $r->person->phone],
                'tour' => ['id' => $r->tour->id, 'name' => $r->tour->name],
                'group' => $r->group?->name,
                'currency' => $r->currency,
                'net_price' => $r->netPrice(),
                'paid' => $r->paidTotal(),
                'balance' => $r->balance(),
                'overdue' => $r->overdue(),
            ]);

        $totals = $items->groupBy('currency')->map(fn (Collection $rows) => [
            'net_price' => $rows->reduce(fn (string $c, array $r) => Money::add($c, $r['net_price']), '0.00'),
            'paid' => $rows->reduce(fn (string $c, array $r) => Money::add($c, $r['paid']), '0.00'),
            'balance' => $rows->reduce(fn (string $c, array $r) => Money::add($c, $r['balance']), '0.00'),
            'overdue' => $rows->reduce(fn (string $c, array $r) => Money::add($c, $r['overdue']), '0.00'),
        ]);

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * @return array{items: Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    public function payments(CollectionFilters $filters): array
    {
        $tourId = $filters->tourId;

        $items = Payment::query()
            ->whereDate('paid_at', '>=', $filters->from->toDateString())
            ->whereDate('paid_at', '<=', $filters->to->toDateString())
            ->when($tourId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('tour_id', $tourId)))
            ->with(['registration:id,person_id,tour_id', 'registration.person:id,first_name,last_name', 'registration.tour:id,name', 'receiver:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'registration_id' => $p->registration_id,
                'person' => $p->registration->person->full_name,
                'tour' => $p->registration->tour->name,
                'type' => $p->type->value,
                'method' => $p->method->label(),
                'amount' => $p->amount,
                'currency' => $p->currency,
                'paid_at' => $p->paid_at->toDateString(),
                'reference' => $p->reference,
                'received_by' => $p->receiver?->name,
            ]);

        // Para birimi bazında net tahsilat (iadeler düşülür).
        $totals = $items->groupBy('currency')->map(function (Collection $rows): array {
            $net = $rows->reduce(fn (string $c, array $r) => $r['type'] === PaymentType::Refund->value
                ? Money::sub($c, $r['amount'])
                : Money::add($c, $r['amount']), '0.00');

            return ['net' => $net, 'count' => (string) $rows->count()];
        });

        return ['items' => $items, 'totals' => $totals];
    }
}
