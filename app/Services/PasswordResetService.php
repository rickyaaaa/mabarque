<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alur lupa password lewat Password Broker Laravel (tabel password_reset_tokens).
 */
class PasswordResetService
{
    /** Pesan generik: sama untuk email terdaftar maupun tidak, supaya tidak membocorkan akun. */
    public const LINK_SENT_MESSAGE = 'Kalau email itu terdaftar, link reset password sudah dikirim. Link berlaku 60 menit.';

    /** @throws ValidationException bila diminta terlalu sering untuk email yang sama */
    public function sendLink(string $email): string
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Link reset baru saja dikirim. Tunggu sebentar sebelum meminta lagi.',
            ])->status(429);
        }

        // RESET_LINK_SENT dan INVALID_USER sengaja dibalas sama.
        return self::LINK_SENT_MESSAGE;
    }

    /**
     * Ganti password memakai token dari email. Setelah berhasil, semua sesi lain dan
     * cookie "ingat saya" milik akun ini dicabut.
     *
     * @param  array{email: string, token: string, password: string}  $input
     *
     * @throws ValidationException token tidak valid/kedaluwarsa
     */
    public function reset(array $input): User
    {
        $resetUser = null;

        $status = Password::broker()->reset(
            ['email' => $input['email'], 'token' => $input['token'], 'password' => $input['password']],
            function (User $user, string $password) use (&$resetUser): void {
                $user->forceFill([
                    'password' => $password, // di-hash oleh cast "hashed"
                    'remember_token' => Str::random(60),
                ])->save();

                $this->revokeSessions($user);
                event(new PasswordReset($user));

                $resetUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET || $resetUser === null) {
            throw ValidationException::withMessages([
                'token' => $status === Password::INVALID_USER
                    ? 'Link reset tidak valid. Minta link baru.'
                    : 'Link reset sudah kedaluwarsa atau sudah dipakai. Minta link baru.',
            ]);
        }

        return $resetUser;
    }

    private function revokeSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
