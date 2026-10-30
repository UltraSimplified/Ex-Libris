<?php

namespace App\Support;

/**
 * A link out of the site, tagged as Good on Paper's referral.
 *
 * The UTM parameters are the ones the advert clicks already send, so the
 * traffic reads the same in anybody's analytics: the source is always Good
 * on Paper, and the campaign names what sent the reader, so a sale or a
 * booking can be credited to it. Whatever query the link already carries
 * is kept, apart from any UTM parameters of its own, which are replaced.
 */
class AffiliateLink
{
    /**
     * The link with Good on Paper's UTM parameters added, or null when it is
     * not a web address.
     */
    public static function tag(string $link, string $campaign, string $medium = 'affiliate'): ?string
    {
        // A bare host such as www.example.com/book has no scheme to parse.
        $url = preg_match('#^[^/:]+\.[^/:]+(/|$)#', $link) ? 'https://'.$link : $link;
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        $kept = array_values(array_filter(
            explode('&', $parts['query'] ?? ''),
            fn (string $pair): bool => $pair !== ''
                && ! in_array(explode('=', $pair, 2)[0], ['utm_source', 'utm_medium', 'utm_campaign'], true)
        ));

        $utm = http_build_query([
            'utm_source' => 'goodonpaper',
            'utm_medium' => $medium,
            'utm_campaign' => $campaign,
        ]);

        $userInfo = isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '';

        return $parts['scheme'].'://'.$userInfo.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .'?'.implode('&', [...$kept, $utm])
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
