<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Platform\ApiKey;
use App\Services\UsageTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the per-tenant API rate limit (requests-per-minute) defined by the
 * tenant's subscription plan. Runs after AuthenticateApiKey so the resolved
 * ApiKey / Tenant are available on the request attributes.
 */
final class EnforcePlanLimits
{
    public function __construct(
        private readonly UsageTracker $usage,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey) {
            return $next($request);
        }

        $tenant = $apiKey->tenant;
        $plan = $tenant->plan;
        $limit = $plan->max_api_rpm;

        if ($limit > 0 && $this->usage->requestsLastMinute($tenant) >= $limit) {
            throw new PlanLimitExceededException(
                "API rate limit of {$limit} requests/minute exceeded for the {$plan->display_name} plan.",
            );
        }

        return $next($request);
    }
}
