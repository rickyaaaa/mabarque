<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\StreamerProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validasi PUT /api/profile — kontrak sama dengan form profile-settings.html. */
class UpdateStreamerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => ($value = trim((string) $this->input($key))) === '' ? null : $value;

        $this->merge([
            'channel_name' => trim((string) $this->input('channel_name')),
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'tagline' => $clean('tagline'),
            'main_role' => ($role = $clean('main_role')) ? strtolower($role) : null,
            'live_schedule' => $clean('live_schedule'),
            'avatar_initials' => ($initials = $clean('avatar_initials')) ? strtoupper($initials) : null,
            'profile_accent' => strtolower(trim((string) $this->input('profile_accent'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'channel_name' => ['required', 'string', 'min:3', 'max:40'],
            'slug' => [
                'required', 'string', 'regex:'.User::SLUG_PATTERN,
                Rule::notIn(User::RESERVED_SLUGS),
                Rule::unique('users', 'slug')->ignore($this->user()->getKey()),
            ],
            'tagline' => ['nullable', 'string', 'max:70'],
            'main_role' => ['nullable', Rule::in(User::MAIN_ROLES)],
            'live_schedule' => ['nullable', 'string', 'max:32'],
            'avatar_initials' => ['nullable', 'string', 'regex:/^[A-Z0-9]{1,2}$/'],
            'profile_accent' => ['required', Rule::in(StreamerProfileService::ACCENTS)],
        ];
    }

    public function messages(): array
    {
        return [
            'channel_name.min' => 'Nama channel minimal 3 karakter.',
            'slug.regex' => 'Pakai 3–24 huruf kecil, angka, atau tanda hubung (tidak di awal/akhir).',
            'slug.not_in' => 'Alamat ini tidak bisa dipakai.',
            'slug.unique' => 'Alamat ini sudah dipakai streamer lain.',
            'avatar_initials.regex' => 'Inisial maksimal 2 huruf atau angka.',
            'profile_accent.in' => 'Pilih salah satu warna aksen yang tersedia.',
        ];
    }
}
