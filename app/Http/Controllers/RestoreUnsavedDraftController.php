<?php

namespace App\Http\Controllers;

use App\Nova\Concerns\RestoresUnsavedDrafts;
use App\Support\UnsavedDrafts;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes back the unsaved changes an Office form's browser kept, to be put
 * into the form when it next loads, for the forms that can take them.
 *
 * The fields come as name and value pairs in the order the form sent them,
 * rather than as an object, so a name the form repeats or writes with
 * brackets arrives as it would have done on Update.
 */
class RestoreUnsavedDraftController extends Controller
{
    public function __invoke(Request $request, string $resource, ?string $resourceId = null): Response
    {
        $resourceClass = Nova::resourceForKey($resource);

        abort_unless(
            $resourceClass !== null && in_array(RestoresUnsavedDrafts::class, class_uses_recursive($resourceClass), true),
            404,
        );

        $fields = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*' => ['array', 'size:2'],
            'fields.*.0' => ['required', 'string', 'max:255'],
            'fields.*.1' => ['nullable', 'string'],
        ])['fields'];

        UnsavedDrafts::keep($request, $resource, $resourceId, array_values($fields));

        return response()->noContent();
    }
}
