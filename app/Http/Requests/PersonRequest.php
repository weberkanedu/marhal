<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Models\Person;
use App\Rules\TcKimlikNo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Person|null $person */
        $person = $this->route('person');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'nationality' => ['required', 'string', 'size:2'],
            'national_id' => [
                'nullable', 'string', 'max:20',
                Rule::when($this->input('nationality') === 'TR', [new TcKimlikNo]),
                $this->uniqueSensitive('whereNationalId', $person, 'Bu T.C. Kimlik No ile kayıtlı bir kişi zaten var.'),
            ],
            'passport_no' => [
                'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 ]+$/',
                $this->uniqueSensitive('wherePassportNo', $person, 'Bu pasaport numarası ile kayıtlı bir kişi zaten var.'),
            ],
            'passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passport_expiry_date' => ['nullable', 'date', 'after:passport_issue_date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'kvkk_consent' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'ad',
            'last_name' => 'soyad',
            'gender' => 'cinsiyet',
            'birth_date' => 'doğum tarihi',
            'nationality' => 'uyruk',
            'national_id' => 'T.C. Kimlik No',
            'passport_no' => 'pasaport no',
            'passport_issue_date' => 'pasaport veriliş tarihi',
            'passport_expiry_date' => 'pasaport geçerlilik tarihi',
            'phone' => 'telefon',
            'email' => 'e-posta',
            'address' => 'adres',
            'emergency_contact_name' => 'acil durum kişisi',
            'emergency_contact_phone' => 'acil durum telefonu',
            'notes' => 'notlar',
            'photo' => 'fotoğraf',
        ];
    }

    /**
     * Modelin doldurabileceği alanlar (fotoğraf ve onay kutusu hariç).
     *
     * @return array<string, mixed>
     */
    public function personData(): array
    {
        return $this->safe()->except(['photo', 'remove_photo', 'kvkk_consent']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nationality' => strtoupper((string) ($this->input('nationality') ?: 'TR')),
            'national_id' => preg_replace('/\s+/', '', (string) $this->input('national_id')) ?: null,
            'kvkk_consent' => $this->boolean('kvkk_consent'),
            'remove_photo' => $this->boolean('remove_photo'),
        ]);
    }

    /**
     * Şifreli alanlarda tekillik: aynı acentede aynı numara ikinci kez girilemez.
     */
    private function uniqueSensitive(string $scope, ?Person $ignore, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scope, $ignore, $message): void {
            if (blank($value)) {
                return;
            }

            // Silinmiş kayıtlar da sayılır (veritabanı tekillik kuralı onları da kapsar).
            $exists = Person::withTrashed()
                ->{$scope}((string) $value)
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($exists) {
                $fail($message);
            }
        };
    }
}
