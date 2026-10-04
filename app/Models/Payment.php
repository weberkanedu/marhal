<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Carbon\CarbonImmutable as Carbon;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tek bir tahsilat / iade hareketi.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $registration_id
 * @property PaymentType $type
 * @property string $amount
 * @property string $currency
 * @property string $exchange_rate
 * @property string $amount_in_registration_currency
 * @property PaymentMethod $method
 * @property Carbon $paid_at
 * @property string|null $reference
 * @property int|null $received_by
 * @property-read Registration $registration
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id', 'tenant_id', 'amount_in_registration_currency'];

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'amount_in_registration_currency' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Kayıt para birimine çevrilmiş tutar her zaman sunucuda hesaplanır.
        static::saving(function (self $payment): void {
            if ($payment->currency === $payment->registration->currency) {
                $payment->exchange_rate = '1';
            }

            $payment->amount_in_registration_currency = Money::mul($payment->amount, $payment->exchange_rate);
        });
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
