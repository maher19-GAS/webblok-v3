<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to suspended / deleted tenants with a friendly status page,
 * while allowing active (and freshly-materialised) tenants through.
 */
final class EnsureTenantActive
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->current();

        if ($tenant === null) {
            abort(404, 'Site not found.');
        }

        if ($tenant->status === TenantStatus::SUSPENDED) {
            abort(403, 'This site is currently suspended.');
        }

        if ($tenant->status === TenantStatus::DELETED) {
            abort(410, 'This site no longer exists.');
        }

        return $next($request);
    }
}
