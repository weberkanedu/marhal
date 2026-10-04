<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taksit planının bir satırı. Ödemeler taksitlere tek tek eşlenmez;
 * vadesi gelen taksit toplamı ödenen toplamla karşılaştırılır (SPEC.md §1 installments).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $registration_id
 * @property Carbon $due_date
 * @property string $amount
 * @property string|null $notes
 */
class Installment extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
