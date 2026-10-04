<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform yöneticisi (super_admin) için acente listesi.
 * Acente oluşturma / paket değiştirme sonraki adımda eklenecek.
 */
class TenantController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->with('plan:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan->name,
                'status' => $tenant->status->value,
                'users_count' => $tenant->users_count,
                'accessible' => $tenant->isAccessible(),
                'created_at' => $tenant->created_at?->toDateString(),
            ]);

        return Inertia::render('platform/Tenants', [
            'tenants' => $tenants,
        ]);
    }
}
