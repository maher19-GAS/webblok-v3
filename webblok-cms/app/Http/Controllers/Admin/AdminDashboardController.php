<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\MarketplaceItem;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\Platform\User;
use Illuminate\Contracts\View\View;

/**
 * Super-admin overview: platform-wide counts and recent activity.
 */
final class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'tenantCount' => Tenant::query()->count(),
            'userCount' => User::query()->count(),
            'planCount' => Plan::query()->where('is_active', true)->count(),
            'marketplaceCount' => MarketplaceItem::query()->where('is_active', true)->count(),
            'recentTenants' => Tenant::query()->with('plan', 'owner')->latest()->limit(10)->get(),
        ]);
    }
}
