<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first Super Administrator from the IBMS_SUPER_ADMIN_* environment values.
 *
 * Does nothing once any Super Administrator exists. If no password is configured, a random one is generated
 * and printed to the console exactly once.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::role(SystemRole::SuperAdministrator->value)->exists()) {
            return;
        }

        $email = config('ibms.super_admin.email');

        if (blank($email)) {
            $this->command?->warn('IBMS_SUPER_ADMIN_EMAIL is not set; no Super Administrator was created.');

            return;
        }

        $password = config('ibms.super_admin.password');
        $generated = blank($password);
        $password = $generated ? Str::password(16) : $password;

        $user = new User([
            'name' => config('ibms.super_admin.name'),
            'email' => Str::lower($email),
            'password' => $password,
        ]);
        $user->forceFill(['is_active' => true, 'email_verified_at' => now()])->save();
        $user->assignRole(SystemRole::SuperAdministrator->value);

        $this->command?->info("Super Administrator created: {$user->email}");

        if ($generated) {
            $this->command?->warn("Generated password (shown once — change it after signing in): {$password}");
        } else {
            $this->command?->warn('Remove IBMS_SUPER_ADMIN_PASSWORD from .env after the first sign-in.');
        }
    }
}
