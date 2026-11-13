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
            'match_count' => ['required', 'integer', 'between:1,99'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
