<?php

namespace App\Http\Requests;

use App\Models\Pool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('pool')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'pts_r32' => ['required', 'integer', 'min:0', 'max:1000'],
            'pts_r16' => ['required', 'integer', 'min:0', 'max:1000'],
            'pts_qf' => ['required', 'integer', 'min:0', 'max:1000'],
            'pts_sf' => ['required', 'integer', 'min:0', 'max:1000'],
            'pts_third' => ['required', 'integer', 'min:0', 'max:1000'],
            'pts_final' => ['required', 'integer', 'min:0', 'max:1000'],

            // Ordered list of all four tie-breaker keys (priority 1 → 4).
            'tiebreakers' => ['required', 'array', 'size:4'],
            'tiebreakers.*' => ['required', 'distinct', Rule::in(array_keys(Pool::TIEBREAKERS))],

            // Local datetime in the pool's timezone (America/Mexico_City); converted to UTC on save.
            'deadline_local' => ['nullable', 'date'],
        ];
    }
}
