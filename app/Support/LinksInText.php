<?php

namespace App\Support;

/**
 * The web addresses written out in a piece of plain text, such as a
 * listing's description, each with where it should lead.
 *
 * An address starts with http://, https:// or www. and runs to the next
 * space. Punctuation it ends on is left out, as that is the sentence's: the
 * full stop after it, or the bracket closing round it.
 *
 * An address elsewhere goes out tagged as Good on Paper's referral, as the
 * listing's ticket button does. One on this site is left as it was: tagged,
 * it would start the reader's visit over in the site's own analytics.
 */
class LinksInText
{
    private const CLOSING_PUNCTUATION = '~[.,;:!?\'’”]$~u';

    /**
     * Each address once, in the order it first appears, with the link it
     * leads to and whether that is somewhere else.
     *
     * @return list<array{text: string, href: string, external: bool}>
     */
    public static function find(string $text, string $campaign): array
    {
        preg_match_all('~\b(?:https?://|www\.)[^\s<>"]+~iu', $text, $matches);

        return collect($matches[0])
            ->map(self::withoutClosingPunctuation(...))
            ->unique()
            ->map(fn (string $address): ?array => self::link($address, $campaign))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The address as a link, or null when it is not a web address after all.
     *
     * @return array{text: string, href: string, external: bool}|null
     */
    private static function link(string $address, string $campaign): ?array
    {
        $tagged = AffiliateLink::tag($address, $campaign);

        if ($tagged === null) {
            return null;
        }

        if (self::isOnThisSite($tagged)) {
            $href = preg_match('~^https?://~i', $address) ? $address : 'https://'.$address;

            return ['text' => $address, 'href' => $href, 'external' => false];
        }

        return ['text' => $address, 'href' => $tagged, 'external' => true];
    }

    /**
     * The address without the punctuation closing the sentence round it. A
     * closing bracket stays where the address opened one itself, as a
     * Wikipedia address can.
     */
    private static function withoutClosingPunctuation(string $address): string
    {
        while (preg_match(self::CLOSING_PUNCTUATION, $address) || self::endsOnUnopenedBracket($address)) {
            $address = mb_substr($address, 0, -1);
        }

        return $address;
    }

    private static function endsOnUnopenedBracket(string $address): bool
    {
        return str_ends_with($address, ')') && substr_count($address, ')') > substr_count($address, '(');
    }

    /**
     * Whether the link leads to this site, with or without its www.
     */
    private static function isOnThisSite(string $url): bool
    {
        $site = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($site) && is_string($host) && self::bareHost($host) === self::bareHost($site);
    }

    private static function bareHost(string $host): string
    {
        return preg_replace('~^www\.~', '', strtolower($host));
    }
}
