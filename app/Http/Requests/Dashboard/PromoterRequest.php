<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\ValidatesBrandColours;
use Illuminate\Foundation\Http\FormRequest;

class PromoterRequest extends FormRequest
{
    use ValidatesBrandColours;

    /**
     * The controller authorises against the policy, which needs the promoter
     * itself to answer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the fields a browser posts in a shape the rules reject.
     *
     * A ticked checkbox arrives as the string "on", which the boolean rule
     * turns down. Empty colour fields mean unset.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_logo' => filter_var($this->input('remove_logo'), FILTER_VALIDATE_BOOL),
            'remove_banner' => filter_var($this->input('remove_banner'), FILTER_VALIDATE_BOOL),
        ]);

        $this->nullEmptyBrandColours();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            ...$this->brandColourRules(),
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_banner' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.max' => 'The logo must be 8MB or smaller.',
            'banner.max' => 'The banner must be 8MB or smaller.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'accent_colour1' => 'accent colour',
            'accent_colour2' => 'second accent colour',
            'brand_background_colour' => 'background colour',
        ];
    }
}
