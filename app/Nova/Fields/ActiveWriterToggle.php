<?php

namespace App\Nova\Fields;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A tick on a writer's page in the Office, and in the Writers list, that
 * makes them active or an alumnus there and then, without opening the edit
 * form. An active writer is listed on Our Writers; an alumnus is not.
 *
 * Never on a form. The edit form keeps an ordinary checkbox for the same
 * column, which is saved with the rest of the form.
 */
class ActiveWriterToggle extends Field
{
    public $component = 'active-writer-toggle';

    public $showOnIndex = false;

    public $showOnCreation = false;

    public $showOnUpdate = false;

    public $textAlign = 'left';

    /**
     * The field is display-only: ticking it saves through the writer
     * status endpoint rather than with a form.
     */
    public function resolve($resource, ?string $attribute = null): void
    {
        $this->value = (bool) $resource->is_active_writer;

        $this->withMeta([
            // Relative, so the request goes to whichever host the Office is
            // being read on rather than to whatever APP_URL happens to say.
            'endpoint' => route('writers.active', $resource, absolute: false),
        ]);
    }

    /**
     * Never write to the model. The status goes through the endpoint.
     */
    public function fill(NovaRequest $request, $model): void
    {
        //
    }
}
