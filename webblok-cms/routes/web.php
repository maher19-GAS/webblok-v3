<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GasCallbackController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cms\BuilderController;
use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\Cms\ExportController;
use App\Http\Controllers\Cms\PageManagerController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\Site\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (driver-conditional)
|--------------------------------------------------------------------------
| The active driver (config/auth_driver.php) decides which routes register.
| Switching drivers is config-only — no code changes required.
*/
$authDriver = config('auth_driver.driver', 'breeze');

if ($authDriver === 'gas') {
    Route::get('/auth/gas', [GasCallbackController::class, 'redirect'])->name('auth.gas.redirect');
    Route::get('/auth/gas/callback', [GasCallbackController::class, 'callback'])->name('auth.gas.callback');
    Route::get('/login', [GasCallbackController::class, 'redirect'])->name('login');
} else {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);
        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register', [RegisterController::class, 'store']);
    });
}

Route::post('/logout', [LogoutController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| CMS (authenticated control panel)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('cms')->name('cms.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Pages panel
    Route::get('/pages', [PageManagerController::class, 'index'])->name('pages.index');
    Route::post('/pages', [PageManagerController::class, 'store'])->name('pages.store');
    Route::post('/pages/{pageId}/publish', [PageManagerController::class, 'publish'])->name('pages.publish');
    Route::post('/pages/{pageId}/homepage', [PageManagerController::class, 'setHomepage'])->name('pages.homepage');
    Route::delete('/pages/{pageId}', [PageManagerController::class, 'destroy'])->name('pages.destroy');

    // Builder
    Route::get('/pages/{pageId}/build', [BuilderController::class, 'edit'])->name('builder.edit');
    Route::get('/pages/{pageId}/tree', [BuilderController::class, 'tree'])->name('builder.tree');
    Route::post('/pages/{pageId}/bloks', [BuilderController::class, 'storeBlok'])->name('builder.bloks.store');
    Route::put('/pages/{pageId}/tree', [BuilderController::class, 'persistTree'])->name('builder.tree.persist');
    Route::post('/pages/{pageId}/revision', [BuilderController::class, 'revision'])->name('builder.revision');

    // Export
    Route::get('/export', [ExportController::class, 'index'])->name('export.index');
    Route::post('/export', [ExportController::class, 'store'])->name('export.store');
    Route::get('/export/{exportId}/download', [ExportController::class, 'download'])->name('export.download');
});

Route::get('/', fn () => redirect()->route('login'))->name('home');

/*
|--------------------------------------------------------------------------
| Web-triggered scheduler (no system cron required)
|--------------------------------------------------------------------------
*/
Route::get('/cron/run', [CronController::class, 'run'])->middleware('throttle:2,1');

/*
|--------------------------------------------------------------------------
| Public tenant site (resolved by host -> tenant.db)
|--------------------------------------------------------------------------
| Registered last so it does not shadow the control-panel routes above.
*/
Route::middleware(['tenant.db', 'tenant.active'])->group(function (): void {
    Route::get('/site/sitemap.xml', [PublicPageController::class, 'sitemap'])->name('site.sitemap');
    Route::get('/site/{path?}', [PublicPageController::class, 'show'])
        ->where('path', '.*')
        ->middleware('track.view')
        ->name('site.show');
});
