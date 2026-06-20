<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Platform\Tenant;
use App\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant owner dashboard: lists the sites the authenticated user owns and the
 * key stats for the currently-selected tenant.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(): View
    {
        $userId = Auth::id();

        $tenants = Tenant::query()
            ->where('owner_id', is_string($userId) ? $userId : '')
            ->orderBy('name')
            ->get();

        return view('cms.dashboard', [
            'tenants' => $tenants,
            'current' => $this->context->current(),
        ]);
    }
}
