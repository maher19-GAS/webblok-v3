<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests via a Bearer token / X-Api-Key header, resolves
 * the owning tenant, switches the tenant DB connection, and (optionally)
 * enforces a required scope passed as a route-middleware parameter.
 *
 * Usage: ->middleware('api.key:bloks:read')
 */
final class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $presented = $this->extractKey($request);

        if ($presented === null) {
            return response()->json(['error' => 'Missing API key.'], 401);
        }

        $apiKey = $this->keys->resolve($presented);

        if ($apiKey === null) {
            return response()->json(['error' => 'Invalid or expired API key.'], 401);
        }

        if ($scope !== null && ! $this->keys->hasScope($apiKey, $scope)) {
            return response()->json(['error' => "Missing required scope: {$scope}."], 403);
        }

        $tenant = $apiKey->tenant;
        $this->context->switchTo($tenant);

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $header = $request->header('X-Api-Key');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }
}
