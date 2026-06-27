<?php

declare(strict_types=1);

use App\Models\Platform\User;
use Illuminate\Support\Str;

function makeUser(string $role): User
{
    return User::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Test '.$role,
        'email' => Str::lower(Str::random(8)).'@example.test',
        'password' => bcrypt('secret'),
        'role' => $role,
    ]);
}

it('redirects guests away from the admin area', function (): void {
    $this->get('/admin')->assertRedirect('/login');
});

it('forbids ordinary users from the admin area', function (): void {
    $this->actingAs(makeUser('tenant_owner'))
        ->get('/admin')
        ->assertForbidden();
});

it('allows super admins into the admin area', function (): void {
    $this->actingAs(makeUser('super_admin'))
        ->get('/admin')
        ->assertOk();
});

it('lets a super admin view the community review queue', function (): void {
    $this->actingAs(makeUser('super_admin'))
        ->get('/admin/community/review')
        ->assertOk();
});
