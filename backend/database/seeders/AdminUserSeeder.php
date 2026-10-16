<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        // Require credentials instead of hardcoding an admin password.
        if (! $email || ! $password) {
            throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD must be configured.');
        }

        $admin = User::firstOrNew(['email' => $email]);

        $admin->name = 'Store Administrator';

        if (! $admin->exists) {
            $admin->password = Hash::make($password);
        }

        $admin->is_admin = true;
        $admin->save();
    }
}
