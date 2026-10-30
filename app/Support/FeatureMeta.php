<?php

namespace App\Support;

use App\Models\Feature;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What a feature page tells Facebook, X, WhatsApp and search engines about
 * itself: the social title, description and image set in the Office, or the
 * feature's own title, excerpt and cover photo when they are left empty,
 * with an Article record in JSON-LD.
 */
class FeatureMeta
{
    /**
     * The name the magazine is published under, as it is written.
     */
    public const PUBLISHER = 'Good on Paper';

    public static function for(Feature $feature): PageMeta
    {
        $title = filled($feature->social_title) ? $feature->social_title : (string) $feature->title;
        $description = filled($feature->social_description)
            ? (string) $feature->social_description
            : (string) Str::of((string) $feature->excerpt)->stripTags()->squish()->limit(200);
        $image = self::imageUrl($feature->social_image ?: $feature->cover_photo);
        $url = route('feature', [$feature->id, $feature->slug]);

        return new PageMeta(
            title: $title,
            description: $description,
            image: $image,
            url: $url,
            type: 'article',
            jsonLd: self::jsonLd($feature, $title, $description, $image, $url),
        );
    }

    private static function imageUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('do')->url($path) : null;
    }

    /**
     * An Article, credited to its writer, or to Good on Paper itself when
     * nobody is, and published by Good on Paper.
     *
     * @return array<string, mixed>
     */
    private static function jsonLd(Feature $feature, string $title, string $description, ?string $image, string $url): array
    {
        // No logo: the site has no square raster mark to point at yet, and an
        // Article's publisher is complete without one.
        $publisher = [
            '@type' => 'Organization',
            'name' => self::PUBLISHER,
            'url' => url('/'),
        ];

        $writer = $feature->user;
        $author = $writer === null || $writer->id === Feature::GOOD_ON_PAPER_USER_ID
            ? ['@type' => 'Organization', 'name' => self::PUBLISHER, 'url' => url('/')]
            : ['@type' => 'Person', 'name' => $writer->name];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => Str::limit($title, 110, ''),
            'description' => $description,
            'image' => $image ? [$image] : null,
            'datePublished' => $feature->published?->toDateString(),
            'dateModified' => $feature->updated_at?->toIso8601String(),
            'author' => $author,
            'publisher' => $publisher,
            'articleSection' => $feature->tag?->tag,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ]);
    }
}
