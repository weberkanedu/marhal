<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Users\ResetUserPassword;
use App\Enums\Feature;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Features\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform yöneticisi (super_admin): acente açma, paket / durum / abonelik yönetimi,
 * acenteye özel modül açıp kapatma ve destek için kullanıcı şifresi sıfırlama.
 * Acentenin iş verisi (yolcu, ödeme) bu ekranlarda gösterilmez (SPEC.md §1 roller).
 */
class TenantController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->with('plan:id,name')
            ->withCount(['users' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan->name,
                'status' => $tenant->status->value,
                'users_count' => $tenant->users_count,
                'accessible' => $tenant->isAccessible(),
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'subscription_ends_at' => $tenant->subscription_ends_at?->toDateString(),
                'created_at' => $tenant->created_at?->toDateString(),
            ]);

        return Inertia::render('platform/Tenants', [
            'tenants' => $tenants,
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request, CreateTenant $create): RedirectResponse
    {
        $data = $request->validate([
            ...$this->tenantRules(),
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], attributes: $this->attributes());

        $result = $create->handle($data);

        Inertia::flash('credentials', [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $result['password'],
            'tenant' => $result['tenant']->name,
        ]);

        return to_route('platform.tenants.show', $result['tenant']);
    }

    public function show(Tenant $tenant, FeatureGate $gate): Response
    {
        $tenant->load('plan.features', 'featureOverrides');
        $planFeatures = $tenant->plan->features->pluck('enabled', 'feature_key');
        $overrides = $tenant->featureOverrides->pluck('enabled', 'feature_key');

        return Inertia::render('platform/TenantShow', [
            'tenant' => [
                ...$tenant->only(['id', 'name', 'plan_id', 'default_currency', 'phone', 'email', 'tursab_no']),
                'status' => $tenant->status->value,
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'subscription_ends_at' => $tenant->subscription_ends_at?->toDateString(),
                'accessible' => $tenant->isAccessible(),
                'active_tours' => $tenant->activeTourCount(),
            ],
            'features' => collect(Feature::cases())->map(fn (Feature $f) => [
                'key' => $f->value,
                'label' => self::featureLabel($f),
                'plan' => (bool) ($planFeatures[$f->value] ?? false),
                'override' => $overrides->has($f->value) ? (bool) $overrides[$f->value] : null,
                'effective' => $gate->allows($tenant, $f),
            ]),
            'users' => $tenant->users()->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate($this->tenantRules(), attributes: $this->attributes());

        $tenant->update([
            ...$data,
            'status' => TenantStatus::from($data['status']),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tenant->name} güncellendi."]);

        return back();
    }

    /**
     * Acenteye özel modül aç/kapat. `enabled = null` paketin varsayılanına döndürür.
     */
    public function updateFeature(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'feature' => ['required', Rule::enum(Feature::class)],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if ($data['enabled'] === null) {
            $tenant->featureOverrides()->where('feature_key', $data['feature'])->get()->each->delete();
        } else {
            $tenant->featureOverrides()->updateOrCreate(
                ['feature_key' => $data['feature']],
                ['enabled' => (bool) $data['enabled']],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modül ayarı kaydedildi.']);

        return back();
    }

    public function resetUserPassword(Tenant $tenant, User $user, ResetUserPassword $reset): RedirectResponse
    {
        abort_unless($user->tenant_id === $tenant->getKey(), 404);

        $password = $reset->handle($user);

        Inertia::flash('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $password]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')],
            'status' => ['required', Rule::enum(TenantStatus::class)],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'default_currency' => ['required', Rule::in(config('marhal.currencies'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tursab_no' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'name' => 'acente adı',
            'plan_id' => 'paket',
            'status' => 'durum',
            'trial_ends_at' => 'deneme bitişi',
            'subscription_ends_at' => 'abonelik bitişi',
            'admin_name' => 'yönetici adı',
            'admin_email' => 'yönetici e-postası',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'plans' => Plan::query()->orderBy('price_monthly')->get(['id', 'name', 'user_limit', 'active_tour_limit']),
            'statuses' => [
                ['value' => TenantStatus::Trial->value, 'label' => 'Deneme'],
                ['value' => TenantStatus::Active->value, 'label' => 'Aktif'],
                ['value' => TenantStatus::Suspended->value, 'label' => 'Askıda'],
            ],
            'currencies' => config('marhal.currencies'),
        ];
    }

    private static function featureLabel(Feature $feature): string
    {
        return match ($feature) {
            Feature::Passengers => 'Yolcu ve tur yönetimi',
            Feature::Payments => 'Ödeme ve tahsilat',
            Feature::BasicReports => 'Temel raporlar (Excel/PDF)',
            Feature::RoomPlanning => 'Oda yerleşimi',
            Feature::BusPlanning => 'Otobüs yerleşimi',
            Feature::FlightLists => 'Uçuş listeleri',
            Feature::BadgeGeneration => 'Yaka kartı',
            Feature::AdvancedReporting => 'Gelişmiş raporlar',
            Feature::ApiAccess => 'API erişimi',
        };
    }
}
