<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VipPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'price' => ['required', 'integer', 'min:1000'],
            'kind' => ['sometimes', 'in:mabar,reward'],
            'reward_label' => ['required_if:kind,reward', 'nullable', 'string', 'min:2', 'max:80'],
            'match_count' => ['required_unless:kind,reward', 'nullable', 'integer', 'between:1,99'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
