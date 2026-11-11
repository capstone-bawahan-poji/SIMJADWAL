<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An integration token (ability schedule:read only, from `php artisan schedule:integration-token`)
 * reads the published schedule and nothing else, whoever's account issued it.
 */
class RejectIntegrationTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->tokenCan('schedule:read') && ! $user->tokenCan('*')) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
