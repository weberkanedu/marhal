<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\Collections\CollectionFilters;
use App\Support\Collections\CollectionQuery;
use App\Support\Collections\CollectionSummary;
use App\Support\TurkishText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tahsilat ekranı: özet (bu ay, gecikmiş), borçlu yolcular, ödemesi tamamlananlar ve tarih aralığındaki
 * tahsilatlar; "Ödeme al" penceresi. Excel/PDF çıktısı ReportController@collections ile aynı sorgudan üretilir.
 */
class CollectionController extends Controller
{
    public function __invoke(Request $request, CollectionQuery $query, CollectionSummary $summary): Response
    {
        Gate::authorize('viewAnyFinance', Tour::class);

        $filters = CollectionFilters::fromRequest($request);
        $canPay = $request->user()?->role->canManagePayments() ?? false;

        return Inertia::render('collections/Index', [
            'tab' => $filters->tab,
            'filters' => $filters->toArray(),
            'tours' => Tour::query()->orderByDesc('start_date')->get(['id', 'name']),
            'rows' => $query->get($filters, perPage: 50),
            'summary' => [
                'outstanding' => $summary->outstanding(),
                'overdue' => $summary->overdue(),
                'due_this_month' => $summary->dueThisMonth(),
                'collected' => $summary->monthlyCollected(),
            ],
            // "Ödeme al" penceresinde seçilecek borçlular (ad sırasıyla); ödeme yetkisi yoksa boş.
            'debtors' => Inertia::defer(fn () => $canPay ? $this->debtors() : []),
            'paymentOptions' => [
                'methods' => PaymentMethod::options(),
                'currencies' => config('marhal.currencies'),
            ],
            'canPay' => $canPay,
        ]);
    }

    /**
     * @return array<int, array{id: string, label: string, tour: string, currency: string, balance: string}>
     */
    private function debtors(): array
    {
        return Registration::query()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->with(['person:id,first_name,last_name', 'tour:id,name'])
            ->withPaidTotal()
            ->get()
            ->filter(fn (Registration $r) => bccomp($r->balance(), '0', 2) > 0)
            ->sort(fn (Registration $a, Registration $b) => TurkishText::compare(
                $a->person->last_name.' '.$a->person->first_name,
                $b->person->last_name.' '.$b->person->first_name,
            ))
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'label' => $r->person->full_name,
                'tour' => $r->tour->name,
                'currency' => $r->currency,
                'balance' => $r->balance(),
            ])
            ->values()
            ->all();
    }
}
