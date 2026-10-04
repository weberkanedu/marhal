<?php

namespace App\Actions\Payments;

use App\Models\Installment;
use App\Models\Registration;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kaydın taksit planını verilen satırlarla değiştirir (boş liste = planı kaldır).
 * Taksitlerin toplamı kaydın net ücretini aşamaz.
 */
class ReplaceInstallmentPlan
{
    /**
     * @param  list<array{due_date: string, amount: numeric, notes?: string|null}>  $rows
     */
    public function handle(Registration $registration, array $rows): void
    {
        $total = array_reduce($rows, fn (string $carry, array $row) => Money::add($carry, $row['amount']), '0.00');

        if (bccomp($total, $registration->netPrice(), 2) > 0) {
            throw ValidationException::withMessages([
                'installments' => "Taksitlerin toplamı ({$total}) net ücreti ({$registration->netPrice()} {$registration->currency}) aşamaz.",
            ]);
        }

        DB::transaction(function () use ($registration, $rows): void {
            $registration->installments()->delete();

            foreach ($rows as $row) {
                $installment = new Installment([
                    'due_date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'notes' => $row['notes'] ?? null,
                ]);
                $installment->tenant_id = $registration->tenant_id;
                $registration->installments()->save($installment);
            }
        });
    }
}
