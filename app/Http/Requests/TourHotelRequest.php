<?php

namespace App\Http\Requests;

use App\Models\Tour;
use App\Models\TourHotel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TourHotelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hotel_id' => [
                'required', 'uuid',
                Rule::exists('hotels', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            // Hiç grup seçilmezse: sadece tek tek yerleştirilen yolcular için (ör. ayrı otelde kalmak isteyenler).
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'uuid',
                Rule::exists('groups', 'id')->where('tour_id', $this->tour()?->getKey())->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hotel_id' => 'otel',
            'check_in' => 'giriş tarihi',
            'check_out' => 'çıkış tarihi',
            'group_ids' => 'gruplar',
            'group_ids.*' => 'grup',
            'notes' => 'notlar',
        ];
    }

    /**
     * Bir grup aynı tarihlerde iki otelde kalamaz (çıkış günü = sonraki girişe izin verilir).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->groupIds() === []) {
                return;
            }

            /** @var TourHotel|null $current */
            $current = $this->route('stay');

            $clash = TourHotel::query()
                ->where('tour_id', $this->tour()?->getKey())
                ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
                ->whereDate('check_in', '<', $this->date('check_out'))
                ->whereDate('check_out', '>', $this->date('check_in'))
                ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $this->groupIds()))
                ->with(['hotel:id,name', 'groups:id,name'])
                ->first();

            if ($clash !== null) {
                $names = $clash->groups->whereIn('id', $this->groupIds())->pluck('name')->join(', ');
                $validator->errors()->add('group_ids', "{$names} bu tarihlerde zaten {$clash->hotel->name} otelinde kalıyor.");
            }
        }];
    }

    /**
     * @return list<string>
     */
    public function groupIds(): array
    {
        return array_values(array_filter((array) $this->input('group_ids', []), 'is_string'));
    }

    private function tour(): ?Tour
    {
        /** @var TourHotel|null $stay */
        $stay = $this->route('stay');

        /** @var Tour|null $tour */
        $tour = $this->route('tour') ?? $stay?->tour;

        return $tour;
    }
}
