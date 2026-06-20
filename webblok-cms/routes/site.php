<?php

declare(strict_types=1);

use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public tenant site routes
|--------------------------------------------------------------------------
| These resolve the active tenant from the request host (subdomain / custom
| domain) and render published pages. They are registered last so the
| platform routes in web.php always take precedence.
|
| Activate by mapping tenant hosts to the app; in local dev use ?tenant=slug.
*/

Route::middleware(['tenant.db', 'tenant.active'])
    ->prefix('site')
    ->group(function (): void {
        Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');

        Route::get('/{path?}', [SiteController::class, 'show'])
            ->where('path', '.*')
            ->middleware('track.pageview')
            ->name('site.show');
    });
