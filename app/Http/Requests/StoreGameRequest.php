<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates a new game title (CLAUDE.md, decision 8). Laravel's global middleware has already
 * trimmed the input and turned a blank value into null.
 */
class StoreGameRequest extends FormRequest
{
    /**
     * Any logged-in user may add a game; the route already requires login.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Collapse runs of whitespace before validating, so "max" counts the title as it will be saved.
     * Skipped when the title contains control characters: squish would turn a line break into a
     * space and hide it from the rule below, which should reject it instead.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name) && preg_match('/\p{Cc}/u', $name) === 0) {
            $this->merge(['name' => Str::squish($name)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // No control characters (line breaks, tabs, …). A positive pattern so invalid UTF-8
                // fails too. Only \p{Cc}: broader classes would reject the zero-width joiner in emoji.
                'regex:/\A\P{Cc}*\z/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a game title.',
            'name.string' => 'Enter a game title.',
            'name.max' => 'Keep the title to 100 characters or fewer.',
            'name.regex' => "Titles can't contain line breaks or special control characters.",
        ];
    }
}
