<?php

namespace App\Http\Controllers;

use App\Enums\PaymentType;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tahsilat ekranı: borçlu yolcular, ödemesi tamamlananlar ve tarih aralığındaki tahsilatlar.
 * Excel/PDF çıktıları Adım 4'te bu sorgular üzerinden üretilecek.
 */
class CollectionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Tour::class);

        $tab = in_array($request->query('tab'), ['borclu', 'tamamlanan', 'tahsilatlar'], true)
            ? $request->query('tab')
            : 'borclu';
        $tourId = is_string($request->query('tour')) && $request->query('tour') !== '' ? $request->query('tour') : null;
        $from = $this->date($request->query('from')) ?? today()->startOfMonth()->toImmutable();
        $to = $this->date($request->query('to')) ?? today()->toImmutable();

        return Inertia::render('collections/Index', [
            'tab' => $tab,
            'filters' => [
                'tour' => $tourId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'tours' => Tour::query()->orderByDesc('start_date')->get(['id', 'name']),
            'rows' => $tab === 'tahsilatlar'
                ? $this->payments($tourId, $from, $to)
                : $this->registrations($tourId, $tab === 'borclu'),
        ]);
    }

    /**
     * @return array{items: Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    private function registrations(?string $tourId, bool $debtors): array
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
    private function payments(?string $tourId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = Payment::query()
            ->whereDate('paid_at', '>=', $from->toDateString())
            ->whereDate('paid_at', '<=', $to->toDateString())
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

        // Para birimi bazında net tahsilat (iadeler düşülür) ve yönteme göre dağılım.
        $totals = $items->groupBy('currency')->map(function (Collection $rows): array {
            $net = $rows->reduce(fn (string $c, array $r) => $r['type'] === PaymentType::Refund->value
                ? Money::sub($c, $r['amount'])
                : Money::add($c, $r['amount']), '0.00');

            return ['net' => $net, 'count' => (string) $rows->count()];
        });

        return ['items' => $items, 'totals' => $totals];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value)?->startOfDay() ?: null;
    }
}
