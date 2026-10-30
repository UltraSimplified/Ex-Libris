<?php

namespace App\Support\Widgets;

/**
 * A widget's settings, read from its options column with a default for
 * anything missing, so an embed already on somebody's site never breaks
 * for want of a key added later.
 */
final class WidgetOptions
{
    public const MAX_COUNT = 50;

    public function __construct(
        public readonly int $count = 12,
        public readonly bool $showImages = true,
        public readonly bool $showPrice = true,
        public readonly bool $showVenue = true,
        public readonly string $buttonText = 'Tickets',
    ) {}

    /**
     * @param  array<string, mixed>|null  $options
     */
    public static function from(?array $options): self
    {
        $options ??= [];
        $defaults = new self;

        $buttonText = trim((string) ($options['button_text'] ?? ''));

        return new self(
            count: max(1, min(self::MAX_COUNT, (int) ($options['count'] ?? $defaults->count))),
            showImages: (bool) ($options['show_images'] ?? $defaults->showImages),
            showPrice: (bool) ($options['show_price'] ?? $defaults->showPrice),
            showVenue: (bool) ($options['show_venue'] ?? $defaults->showVenue),
            buttonText: $buttonText !== '' ? $buttonText : $defaults->buttonText,
        );
    }

    /**
     * @return array{count: int, show_images: bool, show_price: bool, show_venue: bool, button_text: string}
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'show_images' => $this->showImages,
            'show_price' => $this->showPrice,
            'show_venue' => $this->showVenue,
            'button_text' => $this->buttonText,
        ];
    }
}
