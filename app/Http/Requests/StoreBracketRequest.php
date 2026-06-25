<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreBracketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'matchups' => ['required', 'array', 'size:16'],
            'matchups.*.a' => ['required', 'string', 'max:100'],
            'matchups.*.b' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $names = [];
            foreach ((array) $this->input('matchups', []) as $m) {
                foreach (['a', 'b'] as $slot) {
                    $name = trim((string) ($m[$slot] ?? ''));
                    if ($name !== '') {
                        $names[] = mb_strtolower($name);
                    }
                }
            }

            if (count($names) === 32 && count(array_unique($names)) !== 32) {
                $v->errors()->add('matchups', 'All 32 team names must be unique.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'matchups.size' => 'You must provide all 16 Round-of-32 matchups.',
            'matchups.*.a.required' => 'Every matchup needs both teams.',
            'matchups.*.b.required' => 'Every matchup needs both teams.',
        ];
    }
}
