<?php

namespace App\Http\Requests;

use App\Enums\TourStatus;
use App\Enums\TourType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(TourType::class)],
            'status' => ['required', Rule::enum(TourStatus::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', Rule::in(config('marhal.currencies'))],
            'whatsapp_link' => ['nullable', 'url:https', 'starts_with:https://chat.whatsapp.com/', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tur adı',
            'type' => 'tür',
            'status' => 'durum',
            'start_date' => 'başlangıç tarihi',
            'end_date' => 'bitiş tarihi',
            'capacity' => 'kapasite',
            'default_price' => 'varsayılan fiyat',
            'currency' => 'para birimi',
            'whatsapp_link' => 'WhatsApp grup bağlantısı',
            'notes' => 'notlar',
        ];
    }
}
