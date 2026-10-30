<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What a writer's page tells Facebook, X, WhatsApp and search engines
 * about them: their name, their note or a line about what they write, and
 * their picture, with a ProfilePage in JSON-LD.
 */
class WriterMeta
{
    public static function for(User $writer): PageMeta
    {
        $description = filled($writer->bio)
            ? Str::limit((string) Str::of($writer->bio)->stripTags()->squish(), 200)
            : "Articles by {$writer->name} for ".FeatureMeta::PUBLISHER.'.';
        $image = filled($writer->avatar) ? Storage::disk('do')->url($writer->avatar) : null;
        $url = route('writer', Str::slug($writer->name));

        return new PageMeta(
            title: $writer->name,
            description: $description,
            image: $image,
            url: $url,
            type: 'profile',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'url' => $url,
                'mainEntity' => array_filter([
                    '@type' => 'Person',
                    'name' => $writer->name,
                    'description' => filled($writer->bio) ? $description : null,
                    'image' => $image,
                ]),
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => FeatureMeta::PUBLISHER,
                    'url' => url('/'),
                ],
            ],
        );
    }
}
