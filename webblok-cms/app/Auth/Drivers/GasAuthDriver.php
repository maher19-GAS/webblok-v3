<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\AuthDriverInterface;
use App\Exceptions\GasAuthException;
use App\Models\Platform\User;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

final class GasAuthDriver implements AuthDriverInterface
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function attempt(string $email, string $password): ?User
    {
        $cfg = $this->config();

        try {
            $response = $this->http
                ->timeout($cfg['timeout_seconds'])
                ->withHeaders([
                    'X-App-Id' => $cfg['app_id'],
                    'X-Api-Key' => $cfg['api_key'],
                ])
                ->post($cfg['base_url'].'/api/auth/login', compact('email', 'password'));

            if (! $response->ok()) {
                return null;
            }

            /** @var array{token: string, user: array{id: string, email: string, name: string}} $body */
            $body = $response->json();

            return $this->syncGasUser($body['user'], $body['token']);
        } catch (GasAuthException $e) {
            throw $e;
        } catch (Throwable) {
            return null;
        }
    }

    public function verify(Request $request): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null || $user->gas_token === null) {
            return null;
        }

        $cfg = $this->config();

        try {
            $response = $this->http
                ->timeout($cfg['timeout_seconds'])
                ->withHeaders([
                    'X-App-Id' => $cfg['app_id'],
                    'X-Api-Key' => $cfg['api_key'],
                ])
                ->post($cfg['base_url'].'/api/auth/verify', ['token' => $user->gas_token]);

            return $response->ok() ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function loginUrl(): string
    {
        return route('auth.gas.redirect');
    }

    /**
     * @param  array{id: string, email: string, name: string}  $gasUser
     */
    private function syncGasUser(array $gasUser, string $token): User
    {
        if (! (bool) config('auth_driver.gas.user_sync', true)) {
            throw new GasAuthException('GAS user sync is disabled.');
        }

        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['gas_user_id' => $gasUser['id']],
            [
                'id' => (string) Str::uuid(),
                'name' => $gasUser['name'],
                'email' => $gasUser['email'],
                'password' => null,
                'gas_token' => $token,
            ]
        );

        Auth::login($user, remember: true);

        return $user;
    }

    /**
     * @return array{base_url: string, api_key: string, app_id: string, timeout_seconds: int}
     */
    private function config(): array
    {
        /** @var array{base_url: string, api_key: string, app_id: string, timeout_seconds: int} $cfg */
        $cfg = config('auth_driver.gas');

        return $cfg;
    }
}
