<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateTenantUser;
use App\Actions\Users\ResetUserPassword;
use App\Actions\Users\UpdateTenantUser;
use App\Enums\UserRole;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acente yöneticisinin personel (kullanıcı) yönetimi. Rota `role:admin` ile korunur.
 * Geçici şifreler ekranda bir kez gösterilir (flash `credentials`).
 */
class UserController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();

        $users = User::query()
            ->where('tenant_id', $tenant?->getKey())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'is_me' => $request->user()?->is($user) ?? false,
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
            'limit' => [
                'active' => $tenant?->staffCount() ?? 0,
                'max' => $tenant?->plan->user_limit,
            ],
            'roles' => self::roleOptions(),
            // Şüpheli kullanım uyarıları (10d): yalnız bu acentenin; platform "Kullanıcıyı doğrula" ile kapatır.
            'alerts' => $tenant === null ? [] : SecurityAlert::query()->open()->where('tenant_id', $tenant->id)
                ->with('user:id,name')->latest()->get()
                ->map(fn (SecurityAlert $a) => [
                    'id' => $a->id,
                    'user' => $a->user->name,
                    'label' => $a->kind->label(),
                    'summary' => $a->summary(),
                ])->values(),
        ]);
    }

    public function store(Request $request, CreateTenantUser $create): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Operations->value, UserRole::Guide->value])],
        ], attributes: ['name' => 'ad soyad', 'email' => 'e-posta', 'role' => 'rol']);

        $tenant = $this->currentTenant->get();
        abort_if($tenant === null, 403);

        $result = $create->handle($tenant, $data['name'], $data['email'], UserRole::from($data['role']));

        Inertia::flash('credentials', ['email' => $result['user']->email, 'password' => $result['password'], 'name' => $result['user']->name]);

        return back();
    }

    public function update(Request $request, User $user, UpdateTenantUser $update): RedirectResponse
    {
        $this->ensureSameTenant($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::Operations->value, UserRole::Guide->value])],
            'is_active' => ['required', 'boolean'],
        ]);

        $actor = $request->user();
        abort_if($actor === null, 403);

        $update->handle($actor, $user, [
            'name' => $data['name'],
            'role' => $data['role'],
            'is_active' => (bool) $data['is_active'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} güncellendi."]);

        return back();
    }

    public function resetPassword(User $user, ResetUserPassword $reset): RedirectResponse
    {
        $this->ensureSameTenant($user);

        $password = $reset->handle($user);

        Inertia::flash('credentials', ['email' => $user->email, 'password' => $password, 'name' => $user->name]);

        return back();
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function roleOptions(): array
    {
        return [
            ['value' => UserRole::Admin->value, 'label' => 'Yönetici', 'description' => 'Her şeyi görür; personel ve acente ayarlarını yönetir; kimlik no tam görür.'],
            ['value' => UserRole::Operations->value, 'label' => 'Operasyon', 'description' => 'Yolcu, tur, kayıt ve ödeme işlemleri. Kimlik no maskeli.'],
            ['value' => UserRole::Guide->value, 'label' => 'Rehber', 'description' => 'Sadece rehberi olduğu grubun yolcu listesi. Para ve kimlik bilgisi görmez.'],
        ];
    }

    /**
     * Rota model bağlaması kullanıcıyı acente kapsamı olmadan bulur (User modeli kapsamsız);
     * başka acentenin kullanıcısına erişim 404 döner.
     */
    private function ensureSameTenant(User $user): void
    {
        abort_unless($user->tenant_id !== null && $user->tenant_id === $this->currentTenant->id(), 404);
    }
}
