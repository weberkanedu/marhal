<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acente bilgileri: ad, iletişim, TÜRSAB no, varsayılan para birimi ve logo.
 * Bu bilgiler PDF raporlarının ve (Faz 3) yaka kartlarının başlığında kullanılır.
 */
class AgencySettingsController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function edit(): Response
    {
        $tenant = $this->currentTenant->get();
        abort_if($tenant === null, 403);

        return Inertia::render('agency/Edit', [
            'agency' => [
                ...$tenant->only(['name', 'phone', 'email', 'website', 'address', 'city', 'tursab_no', 'default_currency']),
                'logo_url' => $tenant->logo_path ? route('agency.logo').'?v='.$tenant->updated_at?->timestamp : null,
                'plan' => $tenant->plan->name,
            ],
            'currencies' => config('marhal.currencies'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant->get();
        abort_if($tenant === null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:80'],
            'tursab_no' => ['nullable', 'string', 'max:30'],
            'default_currency' => ['required', Rule::in(config('marhal.currencies'))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ], attributes: ['name' => 'acente adı', 'default_currency' => 'varsayılan para birimi']);

        $disk = Storage::disk(config('marhal.media_disk'));

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            if ($tenant->logo_path) {
                $disk->delete($tenant->logo_path);
            }
            $tenant->logo_path = $request->hasFile('logo')
                ? ($request->file('logo')?->storeAs("tenants/{$tenant->id}", 'logo-'.Str::random(6).'.'.$request->file('logo')->extension(), ['disk' => config('marhal.media_disk')]) ?: null)
                : null;
        }

        unset($data['logo'], $data['remove_logo']);
        $tenant->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acente bilgileri kaydedildi.']);

        return back();
    }

    public function logo(): StreamedResponse
    {
        $tenant = $this->currentTenant->get();
        $disk = Storage::disk(config('marhal.media_disk'));

        abort_unless($tenant?->logo_path && $disk->exists($tenant->logo_path), 404);

        return $disk->response($tenant->logo_path, headers: ['Cache-Control' => 'private, max-age=86400']);
    }
}
