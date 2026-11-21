<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::CASHIER,
            'is_active' => true,
            'is_primary' => false,
        ];
    }

    public function superadmin(): static
    {
        return $this->state(fn () => ['role' => Role::SUPERADMIN]);
    }

    public function cashier(): static
    {
        return $this->state(fn () => ['role' => Role::CASHIER]);
    }

    public function adminMaster(): static
    {
        return $this->state(fn () => ['role' => Role::ADMIN_MASTER]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
