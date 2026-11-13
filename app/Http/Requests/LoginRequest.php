<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login streamer dengan email + password. Dibatasi 5 percobaan gagal per email+IP;
 * akun berstatus beta "inactive" (dinonaktifkan Super Admin) ditolak.
 */
class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /** @throws ValidationException */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $guard = Auth::guard('web');

        if (! $guard->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Pesan sama untuk email tak terdaftar & password salah: tidak membocorkan akun.
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        /** @var User $user */
        $user = $guard->user();

        if ($user->beta_status === User::BETA_INACTIVE && ! $user->isSuperAdmin()) {
            $guard->logout();

            throw ValidationException::withMessages(['email' => 'Akun ini sedang dinonaktifkan. Hubungi admin MabarQueue.']);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /** @throws ValidationException */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
        ])->status(429);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->value().'|'.$this->ip());
    }
}
