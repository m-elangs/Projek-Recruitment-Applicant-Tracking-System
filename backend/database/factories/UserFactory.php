<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $role = Role::query()->where('slug', RoleSlug::RECRUITER->value)->first()
            ?? Role::query()->create([
                'slug' => RoleSlug::RECRUITER->value,
                'name' => 'Recruiter',
                'is_system' => true,
            ]);

        return [
            'role_id' => $role->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function withRole(RoleSlug $roleSlug): static
    {
        return $this->state(function () use ($roleSlug) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $roleSlug->value],
                ['name' => ucwords(str_replace('_', ' ', $roleSlug->value)), 'is_system' => true]
            );

            return ['role_id' => $role->id];
        });
    }
}
