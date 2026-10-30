<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What an editable page tells Facebook, X, WhatsApp and search engines
 * about itself: the social title, description and image set in the Office,
 * or the page's own title, the opening of its text and its hero image when
 * they are left empty, with a WebPage record in JSON-LD.
 *
 * Named apart from PageMeta, which is the shape every page's meta takes.
 */
class SitePageMeta
{
    /**
     * The layout attributes that hold a page's text, in the order to look.
     *
     * @var list<string>
     */
    private const TEXT_ATTRIBUTES = ['text', 'col1_text', 'col2_text', 'quote'];

    public static function for(Page $page): PageMeta
    {
        $title = filled($page->social_title) ? $page->social_title : (string) $page->title;
        $description = filled($page->social_description)
            ? (string) $page->social_description
            : (self::opening($page) ?? $title);
        $image = self::imageUrl($page->social_image ?: $page->hero_image);
        $url = route('page', $page->slug);

        return new PageMeta(
            title: $title,
            description: $description,
            image: $image,
            url: $url,
            type: 'website',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'dateModified' => $page->updated_at?->toIso8601String(),
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => FeatureMeta::PUBLISHER,
                    'url' => url('/'),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => FeatureMeta::PUBLISHER,
                    'url' => url('/'),
                ],
            ]),
        );
    }

    /**
     * The first text the page has, as plain words cut to a sentence or two.
     * A body is either Flexible layouts or, on the older pages, plain HTML.
     */
    private static function opening(Page $page): ?string
    {
        $layouts = json_decode((string) $page->body, true);
        $texts = is_array($layouts)
            ? collect($layouts)->flatMap(fn ($layout): array => is_array($layout)
                ? array_values(array_intersect_key($layout['attributes'] ?? [], array_flip(self::TEXT_ATTRIBUTES)))
                : [])
            : collect([(string) $page->body]);

        $opening = $texts
            ->map(fn ($text): string => is_string($text) ? self::plain($text) : '')
            ->first(fn (string $text): bool => $text !== '');

        return $opening === null ? null : Str::limit($opening, 200);
    }

    /**
     * HTML as plain words. A block ending or a line break becomes a space
     * first, so a heading does not run straight into the paragraph after it.
     */
    private static function plain(string $html): string
    {
        $spaced = preg_replace('#</(p|h[1-6]|li|div|blockquote)>|<br\s*/?>#i', ' ', $html) ?? $html;

        return (string) Str::of($spaced)->stripTags()->squish();
    }

    private static function imageUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('do')->url($path) : null;
    }
}
