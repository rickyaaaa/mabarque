<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi pendaftaran streamer. Kontrak sama dengan form signup.html:
 * name, email, handle (username → slug publik), password + password_confirmation, consent.
 */
class RegisterStreamerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    /** Normalisasi sebelum validasi supaya cek unik tidak bisa diakali lewat huruf besar/spasi. */
    protected function prepareForValidation(): void
    {
        $handle = strtolower(ltrim(trim((string) $this->input('handle')), '@'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'handle' => str_replace('_', '-', $handle),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:40'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'handle' => ['required', 'string', 'regex:'.User::SLUG_PATTERN, Rule::notIn(User::RESERVED_SLUGS), Rule::unique('users', 'slug')],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan lupa password.',
            'email.email' => 'Format email tidak valid.',
            'handle.regex' => 'Username 3–24 karakter: huruf kecil, angka, atau tanda hubung di tengah.',
            'handle.not_in' => 'Username ini tidak bisa dipakai.',
            'handle.unique' => 'Username ini sudah dipakai streamer lain.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'consent.accepted' => 'Setujui syarat private alpha untuk melanjutkan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama streamer',
            'handle' => 'username',
        ];
    }
}
