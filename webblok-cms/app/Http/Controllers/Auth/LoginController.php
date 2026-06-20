<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\AuthManager;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Email/password login backed by the active auth driver (Breeze or GAS).
 */
final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->auth->driver()->attempt($credentials['email'], $credentials['password']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
