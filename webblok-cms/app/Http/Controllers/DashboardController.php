<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Platform\Tenant;
use App\Models\Platform\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The signed-in user's home: lists the sites (tenants) they own and provides
 * entry points to the builder, marketplace and account settings.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()
            ->where('owner_id', $user->id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard', [
            'user' => $user,
            'tenants' => $tenants,
        ]);
    }
}
