<?php

namespace App\Http\Requests\Concerns;

/**
 * The details a promoter or a festival is known by, as their owners edit
 * them from the dashboard: name, contact details, about, the four colours,
 * and a logo and banner that can be replaced or removed.
 *
 * The logo and banner are stored by BrandImage rather than mass assigned,
 * so an uploaded filename never arrives by fill().
 */
trait ValidatesBrandProfile
{
    use ValidatesBrandColours;

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
    protected function brandProfileRules(): array
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
     * What may be filled straight onto the record: everything but the
     * pictures and the boxes that clear them, and the promoter and earlier
     * festival a new festival is set up with.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->safe()->except(['logo', 'banner', 'remove_logo', 'remove_banner', 'promoter_id', 'from']);
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
        return $this->brandColourAttributes();
    }
}
