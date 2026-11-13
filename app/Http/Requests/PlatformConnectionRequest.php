<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlatformConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'in:saweria,sociabuzz,trakteer'],
            'label' => ['nullable', 'string', 'max:80'],
            'webhook_url' => ['required', 'url', 'max:2048'],
            'signing_secret' => ['nullable', 'string', 'min:16', 'max:512'],
        ];
    }
}
