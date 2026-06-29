<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles the redirect/callback handshake when the GAS (Global Authenticator
 * System) auth driver is active. With the Breeze driver these routes are not
 * registered.
 */
final class GasCallbackController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $base = config('auth_driver.gas.base_url');
        $appId = config('auth_driver.gas.app_id');
        $baseUrl = is_string($base) ? $base : '';
        $appIdStr = is_string($appId) ? $appId : '';

        $callbackRoute = config('auth_driver.gas.callback_route', '/auth/gas/callback');
        $callback = url(is_string($callbackRoute) ? $callbackRoute : '/auth/gas/callback');

        $target = rtrim($baseUrl, '/').'/oauth/authorize?app_id='.urlencode($appIdStr)
            .'&redirect_uri='.urlencode($callback);

        return redirect()->away($target);
    }

    public function callback(Request $request): RedirectResponse
    {
        // The token exchange is performed by GasAuthDriver::attempt during the
        // standard login flow; this endpoint simply lands the user post-redirect.
        if ($request->query('error') !== null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Authentication with the identity provider failed.',
            ]);
        }

        return redirect()->route('dashboard');
    }
}
