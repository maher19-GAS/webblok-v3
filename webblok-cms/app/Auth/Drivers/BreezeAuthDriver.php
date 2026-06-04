<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\AuthDriverInterface;
use App\Models\Platform\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class BreezeAuthDriver implements AuthDriverInterface
{
    public function attempt(string $email, string $password): ?User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password ?? '')) {
            return null;
        }

        Auth::login($user, remember: true);

        return $user;
    }

    public function verify(Request $request): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function loginUrl(): string
    {
        return route('login');
    }
}
