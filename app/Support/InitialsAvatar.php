<?php

namespace App\Support;

/**
 * The picture shown in the Office for an account with no avatar: their
 * initials in white on a black block.
 *
 * Drawn as an SVG and handed over as a data URL, so it needs no file and
 * no outside service, and is never a stranger's face from Gravatar.
 */
final class InitialsAvatar
{
    /**
     * The picture for this name, ready to use as an image's src.
     */
    public static function for(?string $name): string
    {
        $initials = htmlspecialchars(self::initials($name), ENT_XML1);

        // A width and height of its own, or the Office's tables size it to nothing.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 100 100">'
            .'<rect width="100" height="100" fill="#000"/>'
            .'<text x="50" y="50" dominant-baseline="central" text-anchor="middle" fill="#fff" '
            .'font-family="system-ui, -apple-system, Helvetica, Arial, sans-serif" font-size="40" font-weight="600">'
            .$initials
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The first letter of the first and last words of a name, as the
     * site's own avatars take them: "Robin Layfield" is RL, and a name of
     * one word gives one letter.
     */
    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === [] || $words === false) {
            return '';
        }

        $first = mb_substr($words[0], 0, 1);

        if (count($words) === 1) {
            return mb_strtoupper($first);
        }

        return mb_strtoupper($first.mb_substr($words[array_key_last($words)], 0, 1));
    }
}
