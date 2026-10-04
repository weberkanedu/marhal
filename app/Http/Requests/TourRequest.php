<?php

namespace App\Http\Requests;

use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Models\Tour;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'notes' => 'notlar',
        ];
    }

    /**
     * Paket limiti: aktif olmayan bir tur aktif hale gelirken (veya yeni aktif tur açılırken)
     * "aynı anda aktif tur" sınırı kontrol edilir.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tenant = app(CurrentTenant::class)->get();
            /** @var Tour|null $tour */
            $tour = $this->route('tour');

            $becomesActive = TourStatus::from($this->string('status')->toString())->isActive()
                && $this->date('end_date')?->endOfDay()->isFuture();
            $wasActive = $tour !== null && $tour->status->isActive() && $tour->end_date->endOfDay()->isFuture();

            if ($tenant && $becomesActive && ! $wasActive && ! $tenant->canAddActiveTour()) {
                $validator->errors()->add(
                    'status',
                    "Paketinizde aynı anda en fazla {$tenant->plan->active_tour_limit} aktif tur olabilir. ".
                    'Biten bir turu "Tamamlandı" yapın veya paketinizi yükseltin.',
                );
            }
        }];
    }
}
