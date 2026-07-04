<?php

namespace App\Http\Requests;

use App\Models\Pool;
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
        $count = $this->matchupCount();

        return [
            'matchups' => ['required', 'array', "size:{$count}"],
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

            $expected = $this->matchupCount() * 2;
            if (count($names) === $expected && count(array_unique($names)) !== $expected) {
                $v->errors()->add('matchups', "All {$expected} team names must be unique.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $count = $this->matchupCount();

        return [
            'matchups.size' => "You must provide all {$count} matchups for the starting round.",
            'matchups.*.a.required' => 'Every matchup needs both teams.',
            'matchups.*.b.required' => 'Every matchup needs both teams.',
        ];
    }

    private function matchupCount(): int
    {
        $pool = $this->route('pool');

        return ($pool instanceof Pool) ? $pool->startRoundMatchCount() : 16;
    }
}
