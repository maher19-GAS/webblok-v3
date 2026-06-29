<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Tenants\MaterializeTenant;
use App\Enums\TenantStatus;
use App\Models\Platform\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the request host (subdomain or custom domain)
 * and points the `tenant` database connection at that tenant's SQLite file.
 *
 * Dormant tenants (V3) are materialised on first matching request before the
 * connection is switched, so the database always exists by the time downstream
 * handlers query it.
 */
final class SetTenantDatabaseConnection
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MaterializeTenant $materialize,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            abort(404, 'Site not found.');
        }

        if ($tenant->status === TenantStatus::DORMANT) {
            $this->materialize->execute($tenant);
            $tenant->refresh();
        }

        $this->context->switchTo($tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomainRaw = config('webblok.base_domain', 'webblok.io');
        $baseDomain = is_string($baseDomainRaw) ? $baseDomainRaw : 'webblok.io';

        // Custom domain match takes precedence.
        $byDomain = Tenant::query()->where('domain', $host)->first();
        if ($byDomain !== null) {
            return $byDomain;
        }

        // Subdomain match: {subdomain}.{base_domain}
        if (str_ends_with($host, '.'.$baseDomain)) {
            $subdomain = substr($host, 0, -1 * (strlen($baseDomain) + 1));

            return Tenant::query()->where('subdomain', $subdomain)->first();
        }

        // Fallback: explicit ?tenant=slug for local development.
        $slug = $request->query('tenant');
        if (is_string($slug) && $slug !== '') {
            return Tenant::query()->where('slug', $slug)->first();
        }

        return null;
    }
}
