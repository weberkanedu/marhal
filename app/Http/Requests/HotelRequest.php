<?php

namespace App\Http\Requests;

use App\Enums\HotelCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'city' => ['required', Rule::enum(HotelCity::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'otel adı',
            'city' => 'şehir',
            'address' => 'adres',
            'phone' => 'telefon',
            'stars' => 'yıldız',
            'notes' => 'notlar',
        ];
    }
}
