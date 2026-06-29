<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PageStatus;
use App\Models\Platform\Tenant;
use App\Models\Tenant\Page;
use App\Models\Tenant\StaticExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Web-triggered scheduler replacement (no system cron / SSH required).
 *
 * Each call to run() executes a small batch of maintenance tasks and records
 * the outcome in the platform `cron_runs` table. A platform host hits
 * /cron/run?secret=... once a minute (e.g. via an external uptime pinger).
 */
final class CronRunner
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function run(): void
    {
        $tasks = [
            'process-queue' => fn (): null => $this->processQueue(),
            'publish-scheduled' => fn (): null => $this->publishScheduledPages(),
            'clean-exports' => fn (): null => $this->cleanExpiredExports(),
        ];

        foreach ($tasks as $name => $task) {
            $start = microtime(true);
            try {
                $task();
                $status = 'ok';
            } catch (Throwable $e) {
                $status = 'error: '.$e->getMessage();
            }
            $ms = (int) ((microtime(true) - $start) * 1000);

            DB::table('cron_runs')->insert([
                'task' => $name,
                'status' => $status,
                'duration_ms' => $ms,
                'ran_at' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Drain a small batch from the database queue.
     */
    private function processQueue(): null
    {
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 5,
            '--max-time' => 15,
        ]);

        return null;
    }

    /**
     * Publish pages whose scheduled_at has passed, across all active tenants.
     */
    private function publishScheduledPages(): null
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $this->context->switchTo($tenant);

            Page::query()
                ->where('status', PageStatus::SCHEDULED->value)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', now()->toISOString())
                ->each(function (Page $page): void {
                    $page->forceFill([
                        'status' => PageStatus::PUBLISHED->value,
                        'published_at' => now()->toISOString(),
                    ])->save();
                });
        }

        $this->context->forget();

        return null;
    }

    /**
     * Delete expired static-export archives across active tenants.
     */
    private function cleanExpiredExports(): null
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $this->context->switchTo($tenant);

            StaticExport::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now()->toISOString())
                ->each(function (StaticExport $export): void {
                    if ($export->zip_path !== null && File::exists($export->zip_path)) {
                        File::delete($export->zip_path);
                    }
                    $export->delete();
                });
        }

        $this->context->forget();

        return null;
    }
}
