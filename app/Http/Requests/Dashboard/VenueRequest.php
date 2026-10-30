<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\ValidatesBrandColours;
use Illuminate\Foundation\Http\FormRequest;

class VenueRequest extends FormRequest
{
    use ValidatesBrandColours;

    /**
     * The controller authorises against the policy, which needs the venue
     * itself to answer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A ticked checkbox arrives as the string "on", which the boolean rule
     * would reject and take the rest of the form down with it. Empty colour
     * fields mean unset.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_cover_photo' => filter_var($this->input('remove_cover_photo'), FILTER_VALIDATE_BOOL),
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
            'address' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_cover_photo' => ['nullable', 'boolean'],
            ...$this->brandColourRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cover_photo.max' => 'The cover photo must be 8MB or smaller.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'url' => 'website',
            'phone' => 'phone number',
            'email' => 'email address',
            'cover_photo' => 'cover photo',
            ...$this->brandColourAttributes(),
        ];
    }
}
