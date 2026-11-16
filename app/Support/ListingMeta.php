<?php

// app/Support/ListingMeta.php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Support\Str;

class ListingMeta
{
    public static function for(Listing $listing): PageMeta
    {
        $description = $listing->meta_description
                       ?? Str::of($listing->body)->stripTags()->squish()->limit(160);

        $image = $listing->thumbnail
            ? config('services.cdn.url').'/'.$listing->thumbnail
            : asset('img/listings-placeholder.webp');

        return new PageMeta(
            title: $listing->title.' at '.$listing->venue->name,
            description: (string) $description,
            image: $image,
            type: 'article',
            jsonLd: self::jsonLd($listing, (string) $description, $image),
        );
    }

    protected static function jsonLd(Listing $listing, string $description, string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $listing->title,
            'description' => $description,
            'image' => $image,
            'startDate' => $listing->start_date?->toIso8601String(),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => [
                '@type' => 'Place',
                'name' => $listing->venue->name,
                'address' => $listing->venue->address ?? $listing->venue->name,
            ],
            'offers' => $listing->event_link ? [
                '@type' => 'Offer',
                'url' => $listing->event_link,
                'availability' => 'https://schema.org/InStock',
            ] : null,
        ]);
    }
}
