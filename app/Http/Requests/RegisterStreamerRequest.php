<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

/**
 * Validasi pendaftaran streamer. Handle publik dibuat otomatis dari nama bila tidak dikirim klien.
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
        $name = trim((string) $this->input('name'));
        $handle = strtolower(ltrim(trim((string) $this->input('handle')), '@'));

        if ($handle === '') {
            $handle = $this->uniqueGeneratedHandle($name);
        }

        $this->merge([
            'name' => $name,
            'email' => strtolower(trim((string) $this->input('email'))),
            'handle' => str_replace('_', '-', $handle),
        ]);
    }

    private function uniqueGeneratedHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'streamer';
        $base = rtrim(Str::limit($base, 24, ''), '-');
        $candidate = $base;
        $number = 2;

        while (in_array($candidate, User::RESERVED_SLUGS, true) || User::query()->where('slug', $candidate)->exists()) {
            $suffix = '-'.$number;
            $candidate = rtrim(Str::limit($base, 24 - strlen($suffix), ''), '-').$suffix;
            $number++;
        }

        return $candidate;
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
