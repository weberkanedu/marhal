<?php

namespace App\Http\Controllers;

use App\Actions\Signup\SubmitSignupRequest;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Models\NeedType;
use App\Models\SignupLink;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Telefonla ön kayıt (giriş gerektirmez): acentenin gönderdiği tur linkiyle yolcu pasaportunu okutur
 * (tarayıcıda; fotoğraf sunucuya gelmez), bilgilerini doğrular, ihtiyaçlarını seçer ve gönderir.
 * Link yoksa, kapatıldıysa, tur bittiyse, acente kapalıysa ya da modül kapalıysa 404.
 */
class PublicSignupController extends Controller
{
    public function show(string $token, CurrentTenant $current): Response
    {
        $link = $this->link($token);

        $data = $current->run($link->tenant, function () use ($link): array {
            $link->forceFill(['opened_count' => $link->opened_count + 1])->saveQuietly();

            return [
                'tour' => [
                    'name' => $link->tour->name,
                    'start' => $link->tour->start_date->toDateString(),
                    'end' => $link->tour->end_date->toDateString(),
                ],
                'agency' => ['name' => $link->tenant->name, 'phone' => $link->tenant->phone],
                'needTypes' => NeedType::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name']),
            ];
        });

        return Inertia::render('public/Signup', [...$data, 'token' => $token])->withViewData(['noindex' => true]);
    }

    public function store(Request $request, string $token, CurrentTenant $current, SubmitSignupRequest $submit): RedirectResponse
    {
        $link = $this->link($token);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'nationality' => ['required', 'string', 'size:2'],
            'passport_no' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 ]+$/'],
            'passport_expiry_date' => ['nullable', 'date', 'after:today'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'needs' => ['array', 'max:20'],
            'needs.*' => ['uuid'],
            'kvkk' => ['boolean'],
            'health_consent' => ['boolean'],
            'read_from_passport' => ['boolean'],
        ], [], [
            'first_name' => 'ad', 'last_name' => 'soyad', 'gender' => 'cinsiyet', 'birth_date' => 'doğum tarihi',
            'nationality' => 'uyruk', 'passport_no' => 'pasaport no', 'passport_expiry_date' => 'pasaport geçerlilik tarihi',
            'phone' => 'telefon', 'email' => 'e-posta',
        ]);

        $current->run($link->tenant, fn () => $submit->handle(
            $link,
            $data,
            array_values($data['needs'] ?? []),
            $request->boolean('kvkk'),
            $request->boolean('health_consent'),
            $request->boolean('read_from_passport'),
        ));

        Inertia::flash('signup', ['done' => true]);

        return back();
    }

    private function link(string $token): SignupLink
    {
        $link = SignupLink::query()->withoutGlobalScopes()
            ->where('token_hash', SignupLink::hash($token))
            ->where('is_active', true)
            ->with(['tenant', 'tour' => fn ($q) => $q->withoutGlobalScopes()])
            ->first();

        abort_if(
            $link === null
            || ! $link->tenant->isAccessible()
            // Salt okunur acente yeni başvuru alamaz (kullanıcı kararı 2026-10-07); aile ekranı açık kalır.
            || $link->tenant->isReadOnly()
            || ! $link->tenant->hasFeature(Feature::OnlineSignup)
            || $link->tour->end_date->isPast(),
            404,
        );

        return $link;
    }
}
