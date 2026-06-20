<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\ApiKey;
use App\Models\Platform\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Issues and verifies tenant API keys for the V1 embed and V2 REST APIs.
 *
 * The raw key is shown to the user exactly once; only a SHA-256 hash and a
 * short non-secret prefix are persisted, so a database leak cannot reveal keys.
 */
final class ApiKeyService
{
    /**
     * Generate a new API key for a tenant.
     *
     * @param  list<string>  $scopes
     * @return array{model: ApiKey, plain_key: string}
     */
    public function issue(Tenant $tenant, string $name, array $scopes = ['bloks:read'], string $environment = 'live'): array
    {
        $secret = Str::random(40);
        $prefix = 'wb_'.$environment.'_'.Str::lower(Str::random(8));
        $plain = $prefix.'.'.$secret;

        $model = ApiKey::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => $name,
            'key_hash' => hash('sha256', $plain),
            'key_prefix' => $prefix,
            'environment' => $environment,
            'scopes' => $scopes,
        ]);

        return ['model' => $model, 'plain_key' => $plain];
    }

    /**
     * Resolve a presented raw key to a valid, non-revoked, non-expired ApiKey.
     */
    public function resolve(string $presentedKey): ?ApiKey
    {
        $hash = hash('sha256', $presentedKey);

        $key = ApiKey::query()->where('key_hash', $hash)->first();

        if ($key === null || $key->isRevoked()) {
            return null;
        }

        if ($key->expires_at !== null && $key->expires_at->isPast()) {
            return null;
        }

        $key->forceFill(['last_used_at' => Carbon::now()])->save();

        return $key;
    }

    public function revoke(ApiKey $key): void
    {
        $key->forceFill(['revoked_at' => Carbon::now()])->save();
    }

    public function hasScope(ApiKey $key, string $required): bool
    {
        /** @var list<string> $scopes */
        $scopes = $key->scopes;

        return in_array($required, $scopes, true) || in_array('*', $scopes, true);
    }
}
