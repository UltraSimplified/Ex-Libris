<?php

namespace App\Support;

/**
 * The colours a venue or promoter is known by, ready to paint with.
 *
 * Owners pick up to four colours and may leave any of them unset. What an
 * owner should not have to think about is worked out here: Good on Paper's
 * own palette stands in for anything unset, and the text laid on each
 * colour is black or white, whichever reads, so a yellow button never ends
 * up with white writing on it.
 */
final class Brand
{
    public const BRAND = '#212121';

    public const ACCENT = '#fced3c';

    public const BACKGROUND = '#ffffff';

    /**
     * The columns the colours are stored in on a venue or a promoter, in
     * the order the dashboard offers them.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'brand_colour',
        'accent_colour1',
        'accent_colour2',
        'brand_background_colour',
    ];

    public function __construct(
        public readonly string $brand,
        public readonly string $accent,
        public readonly string $accentTwo,
        public readonly string $background,
    ) {}

    /**
     * A brand from the four stored colours, keyed by their column names.
     * The second accent falls back to the first.
     *
     * @param  array{brand_colour?: ?string, accent_colour1?: ?string, accent_colour2?: ?string, brand_background_colour?: ?string}  $colours
     */
    public static function from(array $colours): self
    {
        $accent = self::valid($colours['accent_colour1'] ?? null) ?? self::ACCENT;

        return new self(
            brand: self::valid($colours['brand_colour'] ?? null) ?? self::BRAND,
            accent: $accent,
            accentTwo: self::valid($colours['accent_colour2'] ?? null) ?? $accent,
            background: self::valid($colours['brand_background_colour'] ?? null) ?? self::BACKGROUND,
        );
    }

    /**
     * The colour for text on the background.
     */
    public function text(): string
    {
        return self::readableOn($this->background) === '#ffffff' ? '#f5f5f5' : '#212121';
    }

    /**
     * Quieter text on the background, for dates and small print: the text
     * colour taken part way towards the background.
     */
    public function mutedText(): string
    {
        return self::mix($this->text(), $this->background, 0.4);
    }

    /**
     * Hairlines and card edges, barely off the background.
     */
    public function line(): string
    {
        return self::mix($this->text(), $this->background, 0.86);
    }

    /**
     * The custom properties a widget's stylesheet reads, and nothing else.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        return [
            '--gop-brand' => $this->brand,
            '--gop-on-brand' => self::readableOn($this->brand),
            '--gop-accent' => $this->accent,
            '--gop-on-accent' => self::readableOn($this->accent),
            '--gop-accent-2' => $this->accentTwo,
            '--gop-on-accent-2' => self::readableOn($this->accentTwo),
            '--gop-bg' => $this->background,
            '--gop-text' => $this->text(),
            '--gop-muted' => $this->mutedText(),
            '--gop-line' => $this->line(),
        ];
    }

    /**
     * Black or white, whichever has the better contrast on the colour.
     */
    public static function readableOn(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#111111') ? '#ffffff' : '#111111';
    }

    /**
     * The WCAG contrast ratio between two colours, from 1 to 21.
     */
    public static function contrast(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($hex));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * The first colour taken the given share of the way towards the second.
     */
    private static function mix(string $from, string $towards, float $share): string
    {
        $mixed = array_map(
            fn (int $start, int $end): int => (int) round($start + ($end - $start) * $share),
            self::channels($from),
            self::channels($towards),
        );

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    /**
     * @return array{int, int, int}
     */
    private static function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * The colour in #rrggbb form, or null when it is not one. Stored values
     * were checked on the way in, but the Office writes them too.
     */
    private static function valid(?string $hex): ?string
    {
        $hex = trim((string) $hex);

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex) === 1 ? strtolower($hex) : null;
    }
}
