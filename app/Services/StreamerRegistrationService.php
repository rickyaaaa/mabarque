<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membuat akun streamer baru beserta data bawaan miliknya (pengaturan overlay default).
 * Akun baru berstatus beta "pending" sampai diaktifkan Super Admin (private alpha).
 */
class StreamerRegistrationService
{
    /**
     * @param  array{name: string, email: string, handle: string, password: string}  $input  data yang sudah divalidasi
     */
    public function register(array $input): User
    {
        try {
            $user = DB::transaction(function () use ($input): User {
                $user = new User([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'], // di-hash otomatis oleh cast "hashed"
                    'channel_name' => $input['name'],
                    'slug' => $input['handle'],
                ]);
                // Ditulis eksplisit: role & beta_status tidak fillable.
                $user->role = User::ROLE_STREAMER;
                $user->beta_status = User::BETA_PENDING;
                $user->save();

                $user->overlaySetting()->create();

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Dua pendaftaran bersamaan dengan email/username sama: validasi lolos, indeks unik DB yang menahan.
            $field = str_contains($exception->getMessage(), 'slug') ? 'handle' : 'email';

            throw ValidationException::withMessages([
                $field => $field === 'email'
                    ? 'Email ini sudah terdaftar. Silakan masuk atau gunakan lupa password.'
                    : 'Username ini sudah dipakai streamer lain.',
            ]);
        }

        event(new Registered($user));

        return $user;
    }

    /** Bentuk data akun yang aman dikirim ke frontend (tanpa password/token). */
    public function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'channel_name' => $user->channel_name,
            'slug' => $user->slug,
            'avatar_initials' => $user->avatar_initials_label,
            'role' => $user->role,
            'beta_status' => $user->beta_status,
        ];
    }
}
