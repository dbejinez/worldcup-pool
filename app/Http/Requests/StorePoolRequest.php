<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may create a pool (they become its manager).
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'method' => ['nullable', \Illuminate\Validation\Rule::in(['full', 'incremental'])],
        ];
    }
}
