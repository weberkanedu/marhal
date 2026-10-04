<?php

namespace App\Http\Controllers;

use App\Actions\Registrations\RegisterPerson;
use App\Actions\Registrations\RemoveRegistration;
use App\Actions\Registrations\UpdateRegistration;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Http\Requests\RegistrationRequest;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Yolcunun tura kaydı. Ekleme/düzenleme ekranı turun detay sayfasıdır (tours/Show);
 * ödemeler ve taksitler kaydın kendi sayfasındadır (registrations/Show).
 */
class RegistrationController extends Controller
{
    public function show(Request $request, Registration $registration): Response
    {
        Gate::authorize('view', $registration->tour);

        $registration->load(['tour', 'group:id,name', 'person']);
        $tour = $registration->tour;
        $person = $registration->person;

        $payments = $registration->payments()
            ->with('receiver:id,name')
            ->latest('paid_at')
            ->latest()
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'type' => $payment->type->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'exchange_rate' => $payment->exchange_rate,
                'amount_in_registration_currency' => $payment->amount_in_registration_currency,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'paid_at' => $payment->paid_at->toDateString(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'received_by' => $payment->receiver?->name,
            ]);

        $installments = $registration->installments()->get()->map(fn (Installment $i) => [
            'id' => $i->id,
            'due_date' => $i->due_date->toDateString(),
            'amount' => $i->amount,
            'notes' => $i->notes,
        ]);

        return Inertia::render('registrations/Show', [
            'registration' => [
                'id' => $registration->id,
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'room_type' => $registration->room_type?->label(),
                'currency' => $registration->currency,
                'price' => $registration->price,
                'discount' => $registration->discount,
                'net_price' => $registration->netPrice(),
                'paid' => $registration->paidTotal(),
                'balance' => $registration->balance(),
                'due_total' => $registration->dueTotal(),
                'overdue' => $registration->overdue(),
                'group' => $registration->group?->name,
            ],
            'tour' => $tour->only(['id', 'name']) + [
                'start_date' => $tour->start_date->toDateString(),
                'end_date' => $tour->end_date->toDateString(),
            ],
            'person' => [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'phone' => $person->phone,
            ],
            'payments' => $payments,
            'installments' => $installments,
            'options' => [
                'methods' => PaymentMethod::options(),
                'types' => PaymentType::options(),
                'currencies' => config('marhal.currencies'),
            ],
            'can' => [
                'pay' => $request->user()?->can('managePayments', $tour) ?? false,
                'deletePayment' => $request->user()?->can('deletePayments', $tour) ?? false,
            ],
        ]);
    }

    public function store(RegistrationRequest $request, Tour $tour, RegisterPerson $register): RedirectResponse
    {
        Gate::authorize('update', $tour);

        $registration = $register->handle($tour, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$registration->person->full_name} tura kaydedildi."]);

        return back();
    }

    public function update(RegistrationRequest $request, Registration $registration, UpdateRegistration $update): RedirectResponse
    {
        Gate::authorize('update', $registration->tour);

        $update->handle($registration, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kayıt güncellendi.']);

        return back();
    }

    public function destroy(Registration $registration, RemoveRegistration $remove): RedirectResponse
    {
        Gate::authorize('update', $registration->tour);

        try {
            $remove->handle($registration);
        } catch (ValidationException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kayıt tur listesinden çıkarıldı.']);

        return back();
    }
}
