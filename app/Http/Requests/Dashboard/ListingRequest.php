<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Listing;
use App\Rules\Embed;
use App\Services\Listings\ListingLineUp;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the checkbox fields before validation.
     *
     * A browser submits a ticked checkbox as the string "on", which the
     * boolean rule rejects — and because one rejected field fails the whole
     * request, an unhandled "on" silently discards every other change in the
     * same save. Coercing here keeps that independent of the exact markup.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_thumbnail' => $this->asBoolean($this->input('remove_thumbnail')),
            'sold_out' => $this->asBoolean($this->input('sold_out')),
        ]);

        $existing = $this->input('gallery_existing');

        if (is_array($existing)) {
            $this->merge([
                'gallery_existing' => array_map(
                    fn ($group) => is_array($group)
                        ? [...$group, 'remove' => $this->asBoolean($group['remove'] ?? null)]
                        : $group,
                    $existing
                ),
            ]);
        }
    }

    /**
     * Read any of the shapes a checkbox can arrive in as a boolean.
     */
    private function asBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'venue_id' => ['nullable', 'integer', $this->mustBeSelectable(
                fn (?Listing $listing = null) => $this->user()->listableVenues($listing),
                'You can only list events at a venue you manage.'
            )],
            'promoter_id' => ['nullable', 'integer', $this->mustBeSelectable(
                fn (?Listing $listing = null) => $this->user()->listablePromoters($listing),
                'You can only list events under a promoter you manage.'
            )],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'additional_date_information' => ['nullable', 'string', 'max:255'],
            'event_time_description' => ['nullable', 'string', 'max:255'],
            'ticket_price' => ['nullable', 'string', 'max:255'],
            'sold_out' => ['boolean'],
            'event_link' => ['nullable', 'url', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            // The artists on the bill: each named, with a playlist, a
            // video or both, and each embed from a provider we accept.
            'line_up' => ['nullable', 'array', 'max:'.ListingLineUp::MAX_ARTISTS],
            'line_up.*.name' => ['required', 'string', 'max:'.ListingLineUp::MAX_NAME],
            'line_up.*.playlist' => ['nullable', 'string', 'required_without:line_up.*.video', Embed::audio()],
            'line_up.*.video' => ['nullable', 'string', Embed::video()],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'gallery_captions' => ['nullable', 'array'],
            'gallery_captions.*' => ['nullable', 'string', 'max:255'],
            'gallery_existing' => ['nullable', 'array'],
            'gallery_existing.*.key' => ['required', 'string', 'max:32'],
            'gallery_existing.*.caption' => ['nullable', 'string', 'max:255'],
            'gallery_existing.*.remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Reject anything the form would not have offered.
     *
     * The selects are narrowed to what this account manages, so the same
     * question has to be asked again on the way in - otherwise a hand-made
     * post could attach an event to somebody else's venue or promoter. The
     * listing being edited is passed through so its current choice stays
     * valid even when it is not one the editor owns.
     */
    private function mustBeSelectable(callable $selectable, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($selectable, $message): void {
            $listing = $this->route('listing');

            if (! $selectable($listing instanceof Listing ? $listing : null)->whereKey($value)->exists()) {
                $fail($message);
            }
        };
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'thumbnail.max' => 'The thumbnail image must be 8MB or smaller.',
            'gallery.max' => 'You can add up to 12 gallery images.',
            'gallery.*.max' => 'Each gallery image must be 8MB or smaller.',
            'line_up.max' => 'You can add up to 6 artists to the line-up.',
            'line_up.*.playlist.required_without' => 'Add a playlist or a video for this artist.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'venue_id' => 'venue',
            'promoter_id' => 'promoter',
            'body' => 'description',
            'thumbnail' => 'thumbnail image',
            'line_up' => 'line-up',
            'line_up.*.name' => 'artist name',
            'line_up.*.playlist' => 'playlist embed',
            'line_up.*.video' => 'video embed',
            'gallery.*' => 'gallery image',
        ];
    }
}
