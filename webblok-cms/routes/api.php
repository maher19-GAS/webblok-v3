<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BlokDataController;
use App\Http\Controllers\Api\V1\SiteGenerationController;
use App\Http\Controllers\Api\V1\TenantProvisionController;
use App\Http\Controllers\Api\V2\BlokInstanceController;
use App\Http\Controllers\Api\V2\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| V1 — Partner-scoped headless provisioning (X-Provision-Secret guarded)
|--------------------------------------------------------------------------
| Registered outside the api.key group: it authenticates with the platform
| provisioning secret, not a tenant API key.
*/
Route::post('/v1/tenants', [TenantProvisionController::class, 'store'])
    ->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| V1 — Embed API (lightweight, API-key authenticated)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware(['api.key:bloks:read', 'plan.limits'])->group(function (): void {
    Route::get('/bloks/{blokKey}', [BlokDataController::class, 'show']);
    Route::post('/sites/generate', [SiteGenerationController::class, 'generate'])
        ->middleware('api.key:sites:write');
});

/*
|--------------------------------------------------------------------------
| V2 — REST CMS API (full content management, API-key authenticated)
|--------------------------------------------------------------------------
*/
Route::prefix('v2')->middleware(['api.key:cms:write', 'plan.limits'])->group(function (): void {
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::post('/pages/{id}/publish', [PageController::class, 'publish']);

    Route::get('/pages/{pageId}/bloks', [BlokInstanceController::class, 'index']);
    Route::post('/pages/{pageId}/bloks', [BlokInstanceController::class, 'store']);
    Route::post('/blok-instances/reorder', [BlokInstanceController::class, 'reorder']);
    Route::get('/pages/{pageId}/render', [BlokInstanceController::class, 'renderPage']);
});
