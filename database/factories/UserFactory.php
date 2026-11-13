<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun streamer MabarQueue. Default: streamer dengan akses beta aktif.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Password bersama untuk semua akun factory (di-hash sekali). */
    protected static ?string $password;

    public function definition(): array
    {
        $channel = fake()->unique()->userName();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password123'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_STREAMER,
            'beta_status' => User::BETA_ACTIVE,
            'channel_name' => Str::limit(Str::headline($channel), 40, ''),
            'slug' => Str::limit(Str::slug($channel), 20, '').'-'.Str::lower(Str::random(3)),
            'profile_accent' => '#f8a917',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /** Baru daftar, menunggu persetujuan private alpha. */
    public function pending(): static
    {
        return $this->state(fn () => ['beta_status' => User::BETA_PENDING]);
    }

    /** Dinonaktifkan Super Admin. */
    public function inactive(): static
    {
        return $this->state(fn () => ['beta_status' => User::BETA_INACTIVE]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SUPER_ADMIN]);
    }
}
