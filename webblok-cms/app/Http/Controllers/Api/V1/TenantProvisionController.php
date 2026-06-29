<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\Platform\User;
use App\Services\TenantMigrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Headless, partner-scoped tenant provisioning (`POST /v1/tenants`).
 *
 * Guarded by the WEBBLOK_PROVISION_SECRET (sent as X-Provision-Secret), this
 * lets a partner integration create a fully-provisioned tenant — platform
 * registry row, owner user and migrated per-tenant SQLite database — in one
 * call, with no SSH or Artisan access required.
 */
final class TenantProvisionController extends Controller
{
    public function __construct(
        private readonly TenantMigrator $migrator,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $secret = config('webblok.provision_secret');
        if (! is_string($secret) || $request->header('X-Provision-Secret') !== $secret) {
            return response()->json(['error' => 'Invalid provision secret.'], 403);
        }

        /** @var array<string, mixed> $v */
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,subdomain'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_name' => ['required', 'string', 'max:120'],
            'plan' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:190', 'unique:tenants,domain'],
        ]);

        $name = is_string($v['name']) ? $v['name'] : 'Tenant';
        $subdomain = is_string($v['subdomain']) ? $v['subdomain'] : Str::lower(Str::random(8));
        $ownerEmail = is_string($v['owner_email']) ? $v['owner_email'] : '';
        $ownerName = is_string($v['owner_name']) ? $v['owner_name'] : 'Owner';
        $planName = is_string($v['plan'] ?? null) ? $v['plan'] : 'free';
        $domain = is_string($v['domain'] ?? null) ? $v['domain'] : null;

        $plan = Plan::query()->where('name', $planName)->where('is_active', true)->first()
            ?? Plan::query()->where('is_active', true)->orderBy('price_monthly')->firstOrFail();

        // Owner user (idempotent on email).
        /** @var User $owner */
        $owner = User::query()->firstOrCreate(
            ['email' => $ownerEmail],
            [
                'id' => (string) Str::uuid(),
                'name' => $ownerName,
                'password' => Hash::make(Str::random(32)),
                'role' => 'tenant_owner',
            ],
        );

        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'domain' => $domain,
            'subdomain' => $subdomain,
            'database_path' => '',
            'storage_path' => '',
            'status' => TenantStatus::PROVISIONING->value,
            'billing_email' => $ownerEmail,
        ]);

        $this->migrator->provision($tenant);

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'slug' => $tenant->slug,
                'subdomain' => $tenant->subdomain,
                'domain' => $tenant->domain,
                'plan' => $plan->name,
                'owner_id' => $owner->id,
                'status' => $tenant->fresh()?->status->value,
            ],
        ], 201);
    }
}
