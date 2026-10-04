<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstallmentPlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'installments' => ['present', 'array', 'max:36'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'installments.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'installments.*.due_date' => 'vade tarihi',
            'installments.*.amount' => 'taksit tutarı',
        ];
    }

    /**
     * @return list<array{due_date: string, amount: numeric, notes?: string|null}>
     */
    public function rows(): array
    {
        /** @var list<array{due_date: string, amount: numeric, notes?: string|null}> $rows */
        $rows = $this->validated('installments');
        usort($rows, fn (array $a, array $b) => strcmp($a['due_date'], $b['due_date']));

        return $rows;
    }
}
