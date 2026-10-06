<?php

namespace App\Support\Subscriptions;

use App\Enums\SubscriptionState;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Support\Plans\PassengerQuota;
use Carbon\CarbonImmutable;

/**
 * Abonelik durumunun ekran hâli tek yerde: üstteki uyarı şeridi, "Paketim" ve platform acente sayfası bunu kullanır.
 */
class SubscriptionSummary
{
    public function __construct(private readonly PassengerQuota $quota) {}

    /**
     * Uygulamanın üstündeki şerit (deneme, gecikmede, salt okunur); gerek yoksa null.
     *
     * @return array{state: string, label: string, tone: string, message: string}|null
     */
    public function banner(Tenant $tenant): ?array
    {
        $state = $tenant->subscriptionState();

        $message = match ($state) {
            SubscriptionState::Trial => $tenant->trial_ends_at === null ? null
                : 'Deneme sürümü: '.self::daysLeft($tenant->trial_ends_at).' gün kaldı. Deneme bitince hesap salt okunur olur; verileriniz silinmez.',
            SubscriptionState::PastDue => 'Ödemeniz bekleniyor. '.$tenant->graceEndsAt()?->translatedFormat('j F Y')
                .' tarihinde hesabınız salt okunur olur.',
            SubscriptionState::ReadOnly => 'Hesabınız salt okunur: bilgileri görebilir ve indirebilirsiniz. Değişiklik için paketinizi yenileyin; verileriniz silinmedi.',
            default => null,
        };

        return $message === null ? null : ['state' => $state->value, 'label' => $state->label(), 'tone' => $state->tone(), 'message' => $message];
    }

    /**
     * "Paketim" ve platform acente sayfası için abonelik ayrıntısı.
     *
     * @return array<string, mixed>
     */
    public function detail(Tenant $tenant): array
    {
        $state = $tenant->subscriptionState();
        $endsAt = $tenant->status === TenantStatus::Trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;

        return [
            'plan' => ['id' => $tenant->plan->id, 'name' => $tenant->plan->name],
            'state' => ['value' => $state->value, 'label' => $state->label(), 'tone' => $state->tone()],
            'billing_cycle' => $tenant->billing_cycle?->value,
            'billing_cycle_label' => $tenant->billing_cycle?->label(),
            'ends_at' => $endsAt?->toDateString(),
            'days_left' => $endsAt !== null && $endsAt->isFuture() ? self::daysLeft($endsAt) : null,
            'grace_ends_at' => $state === SubscriptionState::PastDue ? $tenant->graceEndsAt()?->toDateString() : null,
            'started_at' => $tenant->subscription_started_at?->toDateString(),
            'request' => $tenant->requestedPlan ? [
                'plan_id' => $tenant->requestedPlan->id,
                'plan' => $tenant->requestedPlan->name,
                'billing_cycle' => $tenant->requested_billing_cycle?->value,
                'billing_cycle_label' => $tenant->requested_billing_cycle?->label(),
                'amount' => $tenant->requested_billing_cycle?->priceOf($tenant->requestedPlan),
                'at' => $tenant->requested_at?->toIso8601String(),
            ] : null,
            'usage' => [
                'passengers' => $this->quota->summary($tenant),
                'staff' => ['used' => $tenant->staffCount(), 'limit' => $tenant->plan->user_limit],
            ],
            'payments' => $tenant->subscriptionPayments()->with('plan:id,name')->latest('paid_at')->latest()->limit(24)->get()
                ->map(fn (SubscriptionPayment $p) => [
                    'id' => $p->id,
                    'paid_at' => $p->paid_at->toDateString(),
                    'plan' => $p->plan->name,
                    'billing_cycle_label' => $p->billing_cycle->label(),
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'method_label' => $p->method->label(),
                    'period_starts_at' => $p->period_starts_at->toDateString(),
                    'period_ends_at' => $p->period_ends_at->toDateString(),
                ])->values(),
        ];
    }

    /**
     * Platform → Acenteler tablosunun satırı (tasarımdaki "Yenileme" metni ve kullanım çubukları).
     *
     * @return array<string, mixed>
     */
    public function row(Tenant $tenant): array
    {
        $state = $tenant->subscriptionState();
        $now = CarbonImmutable::now();
        $since = fn (?CarbonImmutable $from) => $from === null ? 0 : max(1, (int) ceil($from->diffInHours($now) / 24));

        $renewal = match ($state) {
            SubscriptionState::Trial => $tenant->trial_ends_at === null ? 'Deneme · süresiz'
                : 'Deneme · '.self::daysLeft($tenant->trial_ends_at).' gün kaldı',
            SubscriptionState::Active => $tenant->subscription_ends_at?->translatedFormat('j M Y') ?? 'Süresiz',
            SubscriptionState::PastDue => 'Ödeme bekleniyor · '.$since($tenant->subscription_ends_at).'. gün',
            SubscriptionState::ReadOnly => 'Salt okunur · '.$since($tenant->status === TenantStatus::Trial
                ? $tenant->trial_ends_at : $tenant->graceEndsAt()).' gündür',
            SubscriptionState::Suspended => 'Elle askıya alındı',
        };

        return [
            'state' => ['value' => $state->value, 'label' => $state->label(), 'tone' => $state->tone()],
            'renewal' => $renewal,
            'usage' => [
                'passengers' => ['used' => $this->quota->used($tenant), 'limit' => $tenant->plan->passenger_limit],
                'staff' => ['used' => $tenant->staffCount(), 'limit' => $tenant->plan->user_limit],
            ],
            'request' => $tenant->requestedPlan ? [
                'plan_id' => $tenant->requestedPlan->id,
                'plan' => $tenant->requestedPlan->name,
                'billing_cycle' => $tenant->requested_billing_cycle?->value,
                'billing_cycle_label' => $tenant->requested_billing_cycle?->label(),
            ] : null,
            'billing_cycle' => $tenant->billing_cycle?->value,
        ];
    }

    private static function daysLeft(CarbonImmutable $end): int
    {
        return max(0, (int) ceil(CarbonImmutable::now()->diffInHours($end, false) / 24));
    }
}
