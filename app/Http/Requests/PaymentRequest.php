<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PaymentType::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'currency' => ['required', Rule::in(config('marhal.currencies'))],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'işlem tipi',
            'amount' => 'tutar',
            'currency' => 'para birimi',
            'exchange_rate' => 'kur',
            'method' => 'ödeme yöntemi',
            'paid_at' => 'ödeme tarihi',
            'reference' => 'makbuz / dekont no',
        ];
    }
}
