<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPERADMIN_PASSWORD');

        if (blank($password)) {
            throw new RuntimeException('Isi SUPERADMIN_PASSWORD di file .env sebelum menjalankan seeder.');
        }

        User::firstOrCreate(
            ['username' => env('SUPERADMIN_USERNAME', 'superadmin')],
            [
                'name' => env('SUPERADMIN_NAME', 'Super Admin'),
                'email' => env('SUPERADMIN_EMAIL', 'superadmin@broseven.test'),
                'password' => $password,
                'role' => Role::SUPERADMIN,
                'is_active' => true,
                'is_primary' => true,
            ]
        );
    }
}
