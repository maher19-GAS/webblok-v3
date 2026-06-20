<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\AuthManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->driver()->logout($request);

        return redirect()->route('home');
    }
}
