<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform super-admins. Anyone else (guests or ordinary
 * tenant owners/editors) is rejected with 403.
 */
final class EnsureSuperAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isSuperAdmin()) {
            abort(403, 'Super-admin access required.');
        }

        return $next($request);
    }
}
