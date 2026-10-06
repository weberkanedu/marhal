<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionPaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acentenin Marhal'a yaptığı abonelik ödemesi. Platform yöneticisi girer (RecordSubscriptionPayment);
 * acente "Paketim"de kendi ödemelerini görür.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property BillingCycle $billing_cycle
 * @property string $amount
 * @property string $currency
 * @property SubscriptionPaymentMethod $method
 * @property Carbon $paid_at
 * @property Carbon $period_starts_at
 * @property Carbon $period_ends_at
 * @property string|null $note
 * @property int|null $recorded_by
 * @property-read Plan $plan
 */
class SubscriptionPayment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'method' => SubscriptionPaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
