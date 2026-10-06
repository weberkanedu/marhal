<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Plan;

/**
 * Abonelik ödeme dönemi. Yıllık ödeyene 2 ay bizden (Plan::YEARLY_MONTHS).
 */
enum BillingCycle: string
{
    use HasOptions;

    case Monthly = 'aylik';
    case Yearly = 'yillik';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Aylık',
            self::Yearly => 'Yıllık',
        };
    }

    public function months(): int
    {
        return $this === self::Yearly ? 12 : 1;
    }

    public function priceOf(Plan $plan): string
    {
        return $this === self::Yearly ? $plan->price_yearly : $plan->price_monthly;
    }
}
