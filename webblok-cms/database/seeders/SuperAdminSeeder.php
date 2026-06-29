<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Platform\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $emailConfig = config('webblok.super_admin.email');
        $email = is_string($emailConfig) ? $emailConfig : 'admin@webblok.test';

        $passwordConfig = config('webblok.super_admin.password');
        $password = is_string($passwordConfig) ? $passwordConfig : 'password';

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ],
        );
    }
}
