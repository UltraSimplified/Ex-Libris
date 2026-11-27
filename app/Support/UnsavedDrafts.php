<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\InputBag;
use Whitecube\NovaFlexibleContent\Http\FlexibleAttribute;
use Whitecube\NovaFlexibleContent\Http\ParsesFlexibleAttributes;

/**
 * An Office form's unsaved changes, kept by the browser as it goes and
 * handed back to be put into the form again.
 *
 * office-unsaved-work.js keeps them as the fields the form would have sent
 * had Update been pressed, pictures aside. Asking for them back parks them
 * in the session for the form's next load, which fills the record with them
 * in memory only: the form opens as it was left, and nothing is written
 * until Update is pressed.
 */
final class UnsavedDrafts
{
    use ParsesFlexibleAttributes;

    private const SESSION_KEY = 'office_unsaved_drafts';

    /**
     * Park a form's unsaved fields for the next time it loads.
     *
     * @param  list<array{0: string, 1: string|null}>  $fields  name and value, in the order the form sent them
     */
    public static function keep(Request $request, string $resource, int|string|null $resourceId, array $fields): void
    {
        $request->session()->put(self::key($resource, $resourceId), self::input($fields));
    }

    /**
     * The form's parked fields as the request Update would have sent, taken
     * from the session so they are put into the form once only. Null when
     * none are waiting, and for anything but the form being loaded.
     */
    public static function pull(NovaRequest $request, string $resource, int|string|null $resourceId): ?NovaRequest
    {
        if (! $request->isMethod('GET') || ! $request->hasSession()) {
            return null;
        }

        $input = $request->session()->pull(self::key($resource, $resourceId));

        return is_array($input) ? (new self)->asRequest($request, $input) : null;
    }

    private static function key(string $resource, int|string|null $resourceId): string
    {
        return self::SESSION_KEY.'.'.$resource.'.'.($resourceId ?? 'new');
    }

    /**
     * The fields as PHP reads them from a submitted form, so a name with
     * brackets in it becomes the array it would have been.
     *
     * @param  list<array{0: string, 1: string|null}>  $fields
     * @return array<string, mixed>
     */
    private static function input(array $fields): array
    {
        $query = implode('&', array_map(
            fn (array $field): string => rawurlencode($field[0]).'='.rawurlencode((string) $field[1]),
            $fields,
        ));

        parse_str($query, $input);

        return $input;
    }

    /**
     * A copy of the form's own request carrying the parked fields as its
     * input, with the body unpacked as the Flexible field's middleware
     * unpacks a real submission.
     *
     * Made a form submission, as Update's is, whatever the request it is
     * copied from was sent as: a copy of a JSON request shares that
     * request's input, and the Flexible field's copies for each block of
     * the body would write over it as they went.
     *
     * @param  array<string, mixed>  $input
     */
    private function asRequest(NovaRequest $request, array $input): NovaRequest
    {
        $draft = NovaRequest::createFrom($request);
        $draft->headers->remove('Content-Type');
        $draft->setJson(new InputBag);
        $draft->setMethod('POST');
        $draft->request->replace($input);

        if ($this->requestHasParsableFlexibleInputs($draft)) {
            $draft->merge($this->getParsedFlexibleInputs($draft));
            $draft->request->remove(FlexibleAttribute::REGISTER);
        }

        return $draft;
    }
}
