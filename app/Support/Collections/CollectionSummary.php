<?php

namespace App\Support\Collections;

use App\Enums\RegistrationStatus;
use App\Models\Installment;
use App\Models\Registration;
use App\Support\Dashboard\CollectionTrend;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Para özetleri (ana panel ve Tahsilat ekranı aynı hesabı kullanır): kalan alacak, gecikmiş borç,
 * bu ay vadesi gelen taksitler, bu ay / geçen ay tahsilat. Para birimleri birbirine eklenmez.
 */
class CollectionSummary
{
    /** @var Collection<int, Registration>|null */
    private ?Collection $active = null;

    public function __construct(private readonly CollectionTrend $trend) {}

    /**
     * @return array<string, string> para birimi → kalan
     */
    public function outstanding(): array
    {
        return $this->sumByCurrency($this->active(), fn (Registration $r) => $r->balance());
    }

    /**
     * @return array{count: int, amounts: array<string, string>}
     */
    public function overdue(): array
    {
        $overdue = $this->active()->filter(fn (Registration $r) => ! Money::isZero($r->overdue()));

        return [
            'count' => $overdue->count(),
            'amounts' => $this->sumByCurrency($overdue, fn (Registration $r) => $r->overdue()),
        ];
    }

    /**
     * Bu ay vadesi gelen (gelmiş + gelecek) taksitlerin toplamı.
     *
     * @return array<string, string>
     */
    public function dueThisMonth(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        return Installment::query()
            ->whereDate('due_date', '>=', $today->startOfMonth()->toDateString())
            ->whereDate('due_date', '<=', $today->endOfMonth()->toDateString())
            ->whereHas('registration', fn ($q) => $q->where('status', '!=', RegistrationStatus::Cancelled))
            ->with('registration:id,currency')
            ->get(['id', 'registration_id', 'amount'])
            ->groupBy(fn (Installment $i) => $i->registration->currency)
            ->map(fn (Collection $items) => $items->reduce(fn (string $c, Installment $i) => Money::add($c, $i->amount), '0.00'))
            ->all();
    }

    /**
     * Bu ay ve geçen ay net tahsilat (ana paneldeki aylık grafikle aynı veri).
     *
     * @return array<string, array{this: string, last: string}>
     */
    public function monthlyCollected(): array
    {
        $series = $this->trend->lastMonths()['series'];

        return collect($series)->map(fn (array $months) => [
            'this' => $months[count($months) - 1] ?? '0.00',
            'last' => $months[count($months) - 2] ?? '0.00',
        ])->all();
    }

    /**
     * @return Collection<int, Registration>
     */
    private function active(): Collection
    {
        return $this->active ??= Registration::query()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->withPaidTotal()
            ->withDueTotal()
            ->get();
    }

    /**
     * @param  Collection<int, Registration>  $registrations
     * @param  callable(Registration): string  $amount
     * @return array<string, string>
     */
    private function sumByCurrency(Collection $registrations, callable $amount): array
    {
        return $registrations
            ->groupBy('currency')
            ->map(fn (Collection $items) => $items->reduce(fn (string $carry, Registration $r) => Money::add($carry, $amount($r)), '0.00'))
            ->reject(fn (string $total) => Money::isZero($total))
            ->all();
    }
}
