<?php

declare(strict_types=1);

use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('webblok.provision_secret', 'test-secret');

    Plan::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'free',
        'display_name' => 'Free',
        'max_pages' => 5,
        'max_bloks' => 50,
        'max_locales' => 1,
        'max_media_mb' => 100,
        'max_exports' => 1,
        'max_api_rpm' => 60,
        'price_monthly' => 0.0,
        'price_yearly' => 0.0,
        'features' => [],
        'is_active' => true,
    ]);
});

it('rejects provisioning without the secret', function (): void {
    $this->postJson('/api/v1/tenants', [
        'name' => 'Acme',
        'subdomain' => 'acme',
        'owner_email' => 'owner@acme.test',
        'owner_name' => 'Owner',
    ])->assertStatus(403);
});

it('provisions a tenant with the correct secret', function (): void {
    $response = $this->withHeaders(['X-Provision-Secret' => 'test-secret'])
        ->postJson('/api/v1/tenants', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            'owner_email' => 'owner@acme.test',
            'owner_name' => 'Owner',
            'plan' => 'free',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.subdomain', 'acme')
        ->assertJsonPath('data.plan', 'free');

    expect(Tenant::query()->where('subdomain', 'acme')->exists())->toBeTrue();
});

it('enforces unique subdomains', function (): void {
    $payload = [
        'name' => 'Acme',
        'subdomain' => 'acme',
        'owner_email' => 'owner@acme.test',
        'owner_name' => 'Owner',
    ];

    $this->withHeaders(['X-Provision-Secret' => 'test-secret'])
        ->postJson('/api/v1/tenants', $payload)->assertStatus(201);

    $this->withHeaders(['X-Provision-Secret' => 'test-secret'])
        ->postJson('/api/v1/tenants', $payload)->assertStatus(422);
});
