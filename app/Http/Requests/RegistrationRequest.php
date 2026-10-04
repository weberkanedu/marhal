<?php

namespace App\Http\Requests;

use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Models\Registration;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tour = $this->tour();
        $creating = $this->route('registration') === null;

        return [
            'person_id' => $creating ? [
                'required', 'uuid',
                Rule::exists('persons', 'id')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->whereNull('deleted_at'),
                Rule::unique('registrations', 'person_id')->where('tour_id', $tour->getKey()),
            ] : ['prohibited'],
            'group_id' => [
                'nullable', 'uuid',
                Rule::exists('groups', 'id')->where('tour_id', $tour->getKey())->whereNull('deleted_at'),
            ],
            'room_type' => ['nullable', Rule::enum(RoomType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'status' => ['required', Rule::enum(RegistrationStatus::class)],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'person_id' => 'yolcu',
            'group_id' => 'grup',
            'room_type' => 'oda tipi',
            'price' => 'fiyat',
            'discount' => 'indirim',
            'status' => 'kayıt durumu',
            'cancel_reason' => 'iptal sebebi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_id.unique' => 'Bu yolcu bu tura zaten kayıtlı.',
            'discount.lte' => 'İndirim fiyattan büyük olamaz.',
        ];
    }

    // Kapasite gibi iş kuralları App\Actions\Registrations içinde; burada sadece girdi biçimi doğrulanır.

    public function tour(): Tour
    {
        /** @var Tour|null $tour */
        $tour = $this->route('tour');

        if ($tour === null) {
            /** @var Registration $registration */
            $registration = $this->route('registration');
            $tour = $registration->tour;
        }

        return $tour;
    }
}
