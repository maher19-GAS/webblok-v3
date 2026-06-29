<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\AuthManager;
use App\Services\Ai\Contracts\SiteGeneratorDriver;
use App\Services\Ai\Drivers\StubSiteGeneratorDriver;
use App\Services\TenantContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthManager::class, fn (Application $app): AuthManager => new AuthManager($app));
        $this->app->singleton(TenantContext::class, fn (): TenantContext => new TenantContext);

        $this->app->bind(SiteGeneratorDriver::class, function (): SiteGeneratorDriver {
            return match (config('webblok.ai_driver', 'stub')) {
                // Real LLM drivers can be bound here when configured.
                default => new StubSiteGeneratorDriver,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Platform migrations live in a dedicated sub-directory so the
        // default `php artisan migrate` only runs the platform schema.
        $this->loadMigrationsFrom(database_path('migrations/platform'));
    }
}
