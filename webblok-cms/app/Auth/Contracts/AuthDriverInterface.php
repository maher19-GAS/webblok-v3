<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Models\Platform\User;
use Illuminate\Http\Request;

interface AuthDriverInterface
{
    /** Attempt login; returns User on success, null on failure. */
    public function attempt(string $email, string $password): ?User;

    /** Verify an existing session/token is still valid. */
    public function verify(Request $request): ?User;

    /** Logout the current user. */
    public function logout(Request $request): void;

    /** Return the login URL (GAS = redirect URL; Breeze = '/login'). */
    public function loginUrl(): string;
}
