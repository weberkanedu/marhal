<?php

namespace App\Http\Middleware;

use App\Enums\FeedbackStatus;
use App\Enums\SubscriptionState;
use App\Models\Feedback;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Subscriptions\SubscriptionSummary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // İlişkiler (acente, paket vb.) her sayfaya gönderilmesin; sadece kullanıcı alanları.
                'user' => $request->user()?->withoutRelations(),
            ],
            // Tembel: `tenant` middleware'i çalıştıktan sonra, sayfa render edilirken değerlendirilir.
            // Acente bağlamı kurulmayan sayfalarda (ör. ayarlar) kullanıcının acentesi kullanılır ki menü eksik kalmasın.
            'tenant' => fn () => $this->tenant($request)?->only(['id', 'name', 'default_currency']),
            'features' => fn () => $this->tenant($request)?->enabledFeatures() ?? [],
            // Deneme / gecikmede / salt okunur uyarı şeridi (SubscriptionSummary).
            // "Görüşünü paylaş" düğmesindeki yeni yanıt noktası.
            'feedbackUnread' => fn () => $this->tenant($request) && $request->user()
                ? Feedback::query()->where('user_id', $request->user()->id)
                    ->where('status', FeedbackStatus::Replied)->whereNull('reply_seen_at')->count()
                : 0,
            // Platform menüsündeki sayılar: ilgilenilecek acente (talep, gecikmede, salt okunur), yanıtlanmamış geri bildirim.
            'platformCounts' => fn () => $request->user()?->isSuperAdmin() ? [
                'tenants' => Tenant::query()->with('plan')->get()->filter(fn (Tenant $t) => $t->requested_plan_id !== null
                    || in_array($t->subscriptionState(), [SubscriptionState::PastDue, SubscriptionState::ReadOnly], true))->count(),
                'feedback' => Feedback::query()->where('status', FeedbackStatus::New)->count(),
            ] : null,
            'subscription' => fn () => ($tenant = $this->tenant($request)) ? app(SubscriptionSummary::class)->banner($tenant) : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function tenant(Request $request): ?Tenant
    {
        /** @var User|null $user */
        $user = $request->user();

        // Askıdaki / süresi dolmuş acentenin modülleri menüde gösterilmez.
        $tenant = app(CurrentTenant::class)->get() ?? $user?->tenant;

        return $tenant?->isAccessible() ? $tenant : null;
    }
}
