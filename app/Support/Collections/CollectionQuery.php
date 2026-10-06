<?php

namespace App\Support\Collections;

use App\Enums\PaymentType;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Borçlu / tamamlanan kayıtlar ve tahsilat hareketleri. Tahsilat ekranı ve
 * Excel/PDF raporları aynı sorguyu kullanır (ekranda görülen = raporda çıkan).
 *
 * Ölçek: kalan borç ve gecikme veritabanında hesaplanır, filtrelenir ve sıralanır;
 * ekran sayfalı çalışır (binlerce kayıtta bile bellek kullanımı sabit).
 */
class CollectionQuery
{
    private const BALANCE = '(price - discount - paid_total)';

    private const OVERDUE = '(CASE WHEN due_total - paid_total > 0 THEN due_total - paid_total ELSE 0 END)';

    /**
     * @return array{items: LengthAwarePaginator<int, mixed>|Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    public function get(CollectionFilters $filters, ?int $perPage = null): array
    {
        return $filters->tab === 'tahsilatlar'
            ? $this->payments($filters, $perPage)
            : $this->registrations($filters->tourId, $filters->tab === 'borclu', $perPage);
    }

    /**
     * @return array{items: LengthAwarePaginator<int, mixed>|Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    public function registrations(?string $tourId, bool $debtors, ?int $perPage = null): array
    {
        $condition = self::BALANCE.($debtors ? ' > 0' : ' <= 0');

        $query = $this->withComputedTotals($tourId)
            ->whereRaw($condition)
            ->orderByRaw(self::OVERDUE.' DESC')
            ->orderByRaw(self::BALANCE.' DESC')
            ->orderBy('registrations.id')
            ->with(['person:id,first_name,last_name,phone,gender,birth_date', 'tour:id,name,start_date', 'group:id,name', 'installments']);

        $map = fn (Registration $r) => [
            'id' => $r->id,
            'person' => ['id' => $r->person->id, 'full_name' => $r->person->full_name, 'phone' => $r->person->phone],
            'tour' => ['id' => $r->tour->id, 'name' => $r->tour->name],
            'group' => $r->group?->name,
            'currency' => $r->currency,
            'net_price' => $r->netPrice(),
            'paid' => $r->paidTotal(),
            'balance' => $r->balance(),
            'overdue' => $r->overdue(),
            // Sonraki taksit ve gecikme günü (tasarımdaki "Sonraki taksit" sütunu).
            'next_due' => ($next = $r->nextUnpaidInstallment())?->due_date->toDateString(),
            'late_days' => $next && $next->due_date->lt(today()) ? (int) $next->due_date->diffInDays(today()) : 0,
            'gender' => $r->person->gender->value,
            'age' => $r->person->birth_date?->age,
        ];

        $items = $perPage
            ? $query->paginate($perPage)->withQueryString()->through($map)
            : $query->get()->map($map)->values();

        $totals = DB::query()
            ->fromSub($this->withComputedTotals($tourId)->toBase(), 'r')
            ->whereRaw($condition)
            ->groupBy('currency')
            ->orderBy('currency')
            ->selectRaw('currency, SUM(price - discount) as net_price, SUM(paid_total) as paid, SUM('.self::BALANCE.') as balance, SUM('.self::OVERDUE.') as overdue')
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->currency => [
                'net_price' => Money::of($row->net_price),
                'paid' => Money::of($row->paid),
                'balance' => Money::of($row->balance),
                'overdue' => Money::of($row->overdue),
            ]]);

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * @return array{items: LengthAwarePaginator<int, mixed>|Collection<int, mixed>, totals: Collection<array-key, mixed>}
     */
    public function payments(CollectionFilters $filters, ?int $perPage = null): array
    {
        $tourId = $filters->tourId;

        $base = fn () => Payment::query()
            ->whereDate('paid_at', '>=', $filters->from->toDateString())
            ->whereDate('paid_at', '<=', $filters->to->toDateString())
            ->when($tourId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('tour_id', $tourId)));

        $query = $base()
            ->with(['registration:id,person_id,tour_id', 'registration.person:id,first_name,last_name', 'registration.tour:id,name', 'receiver:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        $map = fn (Payment $p) => [
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
        ];

        $items = $perPage
            ? $query->paginate($perPage)->withQueryString()->through($map)
            : $query->get()->map($map)->values();

        // Para birimi bazında net tahsilat (iadeler düşülür), veritabanında.
        $totals = $base()
            ->toBase()
            ->groupBy('currency')
            ->orderBy('currency')
            ->selectRaw('currency, COUNT(*) as count, SUM(CASE WHEN type = ? THEN -amount ELSE amount END) as net', [PaymentType::Refund->value])
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->currency => [
                'net' => Money::of($row->net),
                'count' => (string) $row->count,
            ]]);

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * İptal edilmemiş kayıtlar + `paid_total` ve `due_total` hesaplanmış sütunları,
     * dış sorguda filtrelenebilsin diye alt sorgu olarak.
     *
     * @return Builder<Registration>
     */
    private function withComputedTotals(?string $tourId): Builder
    {
        $inner = Registration::query()
            ->select('registrations.*')
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->when($tourId, fn ($q) => $q->where('tour_id', $tourId))
            ->withPaidTotal()
            ->withDueTotal();

        return Registration::query()->fromSub($inner, 'registrations');
    }
}
