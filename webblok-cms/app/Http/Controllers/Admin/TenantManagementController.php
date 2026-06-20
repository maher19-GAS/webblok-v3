<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Platform\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Super-admin tenant lifecycle controls (suspend / reactivate).
 */
final class TenantManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.tenants', [
            'tenants' => Tenant::query()->with('plan', 'owner')->orderByDesc('created_at')->paginate(25),
        ]);
    }

    public function suspend(string $tenantId): RedirectResponse
    {
        Tenant::query()->findOrFail($tenantId)
            ->forceFill(['status' => TenantStatus::SUSPENDED->value])->save();

        return back();
    }

    public function reactivate(string $tenantId): RedirectResponse
    {
        Tenant::query()->findOrFail($tenantId)
            ->forceFill(['status' => TenantStatus::ACTIVE->value])->save();

        return back();
    }
}
