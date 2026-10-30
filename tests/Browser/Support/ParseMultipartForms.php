<?php

namespace Tests\Browser\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the fields out of a multipart form for the browser tests' server.
 *
 * Pest's in-process server only parses form-encoded bodies, and Nova saves
 * a form as multipart FormData, posting with _method=PUT. Left unread, the
 * save reached Laravel as a bare POST with no fields and came back "Method
 * Not Allowed". Uploaded files are skipped; nothing in these tests sends one.
 */
class ParseMultipartForms
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentType = (string) $request->headers->get('Content-Type');

        if ($request->request->count() === 0 && str_starts_with(strtolower($contentType), 'multipart/form-data')) {
            $request->request->replace(self::fields($request->getContent(), $contentType));

            // Clears the method read before the fields were there, so _method counts.
            $request->setMethod('POST');
        }

        return $next($request);
    }

    /**
     * The text fields of a multipart body, nested as PHP would nest them.
     *
     * @return array<string, mixed>
     */
    public static function fields(string $body, string $contentType): array
    {
        if (! preg_match('/boundary="?([^";]+)"?/i', $contentType, $boundary)) {
            return [];
        }

        $pairs = [];

        foreach (explode('--'.$boundary[1], $body) as $part) {
            [$head, $value] = array_pad(explode("\r\n\r\n", ltrim($part, "\r\n"), 2), 2, null);

            if ($value === null || str_contains($head, 'filename=') || ! preg_match('/name="([^"]*)"/', $head, $name)) {
                continue;
            }

            $pairs[] = $name[1].'='.rawurlencode(substr($value, 0, -2));
        }

        parse_str(implode('&', $pairs), $fields);

        return $fields;
    }
}
