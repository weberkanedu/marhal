<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kullanım: ->middleware('feature:room_planning')
 * `tenant` middleware'inden sonra çalışmalıdır.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->currentTenant->get();

        abort_unless($tenant?->hasFeature($feature), 403, 'Bu modül paketinizde bulunmuyor.');

        return $next($request);
    }
}
