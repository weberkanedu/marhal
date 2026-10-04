<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\PaymentType;
use App\Http\Requests\InstallmentPlanRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Tahsilat, iade ve taksit planı. Ekranı kaydın ödeme sayfasıdır (registrations/Show).
 */
class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Registration $registration, RecordPayment $record): RedirectResponse
    {
        Gate::authorize('managePayments', $registration->tour);

        $payment = $record->handle($registration, $request->validated(), $request->user());

        $label = $payment->type === PaymentType::Refund ? 'İade' : 'Tahsilat';
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$label} kaydedildi: {$payment->amount} {$payment->currency}"]);

        return back();
    }

    /**
     * Hatalı girilen ödemeyi siler (yumuşak silme; erişim kaydında kalır).
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        Gate::authorize('deletePayments', $payment->registration->tour);

        $payment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ödeme kaydı silindi.']);

        return back();
    }

    public function updateInstallments(InstallmentPlanRequest $request, Registration $registration, ReplaceInstallmentPlan $replace): RedirectResponse
    {
        Gate::authorize('managePayments', $registration->tour);

        $replace->handle($registration, $request->rows());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taksit planı kaydedildi.']);

        return back();
    }
}
