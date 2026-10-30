<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Normalise the checkbox before validation.
     *
     * A browser submits a ticked checkbox as the string "on", which the
     * boolean rule rejects — and one rejected field fails the whole request,
     * so an unhandled "on" would take the name and email down with it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_avatar' => filter_var($this->input('remove_avatar'), FILTER_VALIDATE_BOOL),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_avatar' => ['nullable', 'boolean'],
            /*
             * The byline. Only a writer is shown these, and only a writer
             * has them saved - but they are validated for everybody, so a
             * field posted by somebody who was never offered it is turned
             * away on its merits rather than silently ignored.
             */
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.max' => 'The profile picture must be 8MB or smaller.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'profile picture',
            'bio' => 'byline',
            'avatar_alt_text' => 'picture description',
        ];
    }
}
