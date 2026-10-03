<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Director,
            'department_id' => null,
        ];
    }

    public function unitOwner(Department $department): static
    {
        return $this->state(fn () => ['role' => Role::UnitOwner, 'department_id' => $department->id]);
    }

    public function auditor(): static
    {
        return $this->state(fn () => ['role' => Role::Auditor]);
    }

    public function riskOfficer(): static
    {
        return $this->state(fn () => ['role' => Role::RiskOfficer]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
