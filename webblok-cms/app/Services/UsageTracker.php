<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\Tenant;
use App\Models\Platform\UsageLog;
use Illuminate\Support\Facades\DB;

/**
 * Records per-tenant API usage (daily aggregated rows) and exposes the counters
 * used by plan-limit enforcement and the admin dashboards.
 */
final class UsageTracker
{
    public function recordApiRequest(
        string $tenantId,
        string $blokKey,
        string $responseType,
        string $lang = 'en',
        ?string $apiKeyId = null,
        ?int $renderMs = null,
    ): void {
        $today = now()->toDateString();

        $existing = UsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('api_key_id', $apiKeyId)
            ->where('blok_key', $blokKey)
            ->where('response_type', $responseType)
            ->where('lang', $lang)
            ->where('log_date', $today)
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'requests' => $existing->requests + 1,
                'render_ms' => $renderMs ?? $existing->render_ms,
            ])->save();

            return;
        }

        UsageLog::query()->create([
            'tenant_id' => $tenantId,
            'api_key_id' => $apiKeyId,
            'blok_key' => $blokKey,
            'response_type' => $responseType,
            'lang' => $lang,
            'requests' => 1,
            'render_ms' => $renderMs,
            'log_date' => $today,
            'created_at' => now()->toISOString(),
        ]);
    }

    /**
     * Total API requests issued by a tenant in the current calendar month.
     */
    public function monthlyRequests(Tenant $tenant): int
    {
        $sum = UsageLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('log_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('requests');

        return (int) $sum;
    }

    /**
     * Approximate requests in the trailing 60 seconds for rate-limit checks.
     * Falls back to the daily counter when fine-grained data is unavailable.
     */
    public function requestsLastMinute(Tenant $tenant): int
    {
        $count = DB::connection(config('database.default') === 'mysql' ? 'mysql' : 'sqlite')
            ->table('usage_logs')
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subMinute()->toISOString())
            ->sum('requests');

        return (int) $count;
    }
}
