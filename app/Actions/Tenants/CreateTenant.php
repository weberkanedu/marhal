<?php

namespace App\Actions\Tenants;

use App\Actions\Users\CreateTenantUser;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Support\Needs\DefaultNeedTypes;
use App\Support\Readiness\DefaultReadinessItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Platform yöneticisinin yeni acente açması: acente + ilk yönetici hesabı (geçici şifre).
 */
class CreateTenant
{
    public function __construct(private readonly CreateTenantUser $createUser) {}

    /**
     * @param  array{name: string, plan_id: string, status: string, trial_ends_at?: string|null, subscription_ends_at?: string|null, default_currency: string, phone?: string|null, email?: string|null, tursab_no?: string|null, city?: string|null, admin_name: string, admin_email: string}  $data
     * @return array{tenant: Tenant, password: string}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'plan_id' => $data['plan_id'],
                'status' => TenantStatus::from($data['status']),
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
                'default_currency' => $data['default_currency'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'tursab_no' => $data['tursab_no'] ?? null,
                'city' => $data['city'] ?? null,
            ]);

            // Varsayılan ihtiyaç türleri ve hazırlık maddeleri (acente sonradan değiştirebilir).
            DefaultNeedTypes::seed($tenant);
            DefaultReadinessItems::seed($tenant);

            $result = $this->createUser->handle(
                $tenant,
                $data['admin_name'],
                $data['admin_email'],
                UserRole::Admin,
                enforceLimit: false,
            );

            return ['tenant' => $tenant, 'password' => $result['password']];
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '-', 'tr') ?: 'acente';
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
