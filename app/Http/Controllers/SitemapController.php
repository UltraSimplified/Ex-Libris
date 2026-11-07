<?php

namespace App\Http\Controllers;

use App\Support\Sitemap;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml, drawn up from the database when asked for rather than kept
 * as a file, so it can never fall behind what is on. Kept for an hour at a
 * time: crawlers come by far less often than listings change, and an hour
 * late is still sooner than they would find a new event by following links.
 */
class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    private const CACHE_SECONDS = 3600;

    public function __invoke(): Response
    {
        $xml = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_SECONDS,
            fn (): string => '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .view('sitemap', ['entries' => Sitemap::entries()])->render(),
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
        ]);
    }
}
