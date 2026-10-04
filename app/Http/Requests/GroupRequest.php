<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Group|null $group */
        $group = $this->route('group');
        /** @var Tour|null $tour */
        $tour = $this->route('tour') ?? $group?->tour;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('groups', 'name')
                    ->where('tour_id', $tour?->getKey())
                    ->ignore($group?->getKey()),
            ],
            'guide_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->where('role', UserRole::Guide->value),
            ],
            'guide_name' => ['nullable', 'string', 'max:150'],
            'guide_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'grup adı',
            'guide_user_id' => 'rehber',
            'guide_name' => 'rehber adı',
            'guide_phone' => 'rehber telefonu',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'Bu turda aynı isimde bir grup zaten var.'];
    }
}
