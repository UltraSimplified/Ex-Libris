<?php

namespace App\Rules;

use App\Services\Embeds\EmbedSanitiser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when an embed cannot be reduced to a usable iframe from an
 * allowed provider, so the promoter is told why rather than saving
 * successfully and finding the field silently empty.
 */
final class Embed implements ValidationRule
{
    public function __construct(
        private readonly string $kind,
        private readonly string $providers,
    ) {}

    /**
     * A playlist embed from Bandcamp, Spotify, Tidal or Qobuz.
     */
    public static function audio(): self
    {
        return new self('audio', 'Bandcamp, Spotify, Tidal or Qobuz');
    }

    /**
     * A video embed or link from YouTube or Vimeo.
     */
    public static function video(): self
    {
        return new self('video', 'YouTube or Vimeo');
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $sanitiser = app(EmbedSanitiser::class);

        $clean = $this->kind === 'audio'
            ? $sanitiser->audio((string) $value)
            : $sanitiser->video((string) $value);

        if ($clean === null) {
            $fail("The :attribute must be an embed code from {$this->providers}. Copy the whole embed code from the provider and paste it here.");
        }
    }
}
