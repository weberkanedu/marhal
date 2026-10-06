<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\FamilyLink;
use App\Support\Family\FamilyView;
use App\Support\Tenancy\CurrentTenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aile ekranı (giriş gerektirmez): yolcunun ailesi linkle yolcunun nerede olduğunu ve günün programını görür.
 * Link yoksa, iptal edildiyse, süresi bittiyse, acente kapalıysa ya da modül kapalıysa 404.
 */
class PublicFamilyController extends Controller
{
    public function __invoke(string $token, CurrentTenant $current, FamilyView $view): Response
    {
        $link = FamilyLink::query()->withoutGlobalScopes()
            ->where('token_hash', FamilyLink::hash($token))
            ->active()
            ->with('tenant')
            ->first();

        abort_if($link === null || ! $link->tenant->isAccessible() || ! $link->tenant->hasFeature(Feature::FamilyScreen), 404);

        $data = $current->run($link->tenant, function () use ($link, $view): array {
            $link->forceFill(['last_viewed_at' => now(), 'view_count' => $link->view_count + 1])->saveQuietly();

            return $view->for($link, $link->tenant);
        });

        return Inertia::render('public/Family', ['family' => $data])
            ->withViewData(['noindex' => true]);
    }
}
