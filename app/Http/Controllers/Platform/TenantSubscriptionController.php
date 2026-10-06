<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Subscriptions\ExtendAccess;
use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Enums\BillingCycle;
use App\Enums\SubscriptionPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Platform yöneticisi → acente: "Ödeme geldi" (dönem açar) ve "+7 gün" (süre uzatır). Kurallar Action'larda.
 */
class TenantSubscriptionController extends Controller
{
    public function payment(Request $request, Tenant $tenant, RecordSubscriptionPayment $record): RedirectResponse
    {
        /** @var array{plan_id: string, billing_cycle: string, amount: string, method: string, paid_at: string, note?: string|null} $data */
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'method' => ['required', Rule::enum(SubscriptionPaymentMethod::class)],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'plan_id' => 'paket', 'billing_cycle' => 'dönem', 'amount' => 'tutar', 'method' => 'yöntem', 'paid_at' => 'ödeme tarihi',
        ]);

        $payment = $record->handle($tenant, $data, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$tenant->name}: ödeme işlendi, hesap açıldı · yeni dönem sonu ".$payment->period_ends_at->translatedFormat('j F Y').'.',
        ]);

        return back();
    }

    public function extend(Tenant $tenant, ExtendAccess $extend): RedirectResponse
    {
        $extend->handle($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tenant->name}: süre ".ExtendAccess::DAYS.' gün uzatıldı.']);

        return back();
    }
}
