<?php

namespace App\Nova\Fields;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use LogicException;

/**
 * A line at the head of a form that keeps what is typed into it, in the
 * browser, until it is saved, and offers it back on the form's next visit
 * when it was never saved: when the tab was closed, the browser was signed
 * out, or the form was left for another page. See office-unsaved-work.js.
 *
 * Only on forms, and never written to the record. The resource it is on
 * needs RestoresUnsavedDrafts too, to put the changes back into the form.
 */
class UnsavedChanges extends Field
{
    public $component = 'gop-unsaved-changes';

    public $showOnIndex = false;

    public $showOnDetail = false;

    /**
     * Where to send kept changes to have them back, and when the record was
     * last saved, so an offer of older changes can say they are older.
     */
    public function resolve($resource, ?string $attribute = null): void
    {
        if (! $resource instanceof Model) {
            throw new LogicException('Unsaved changes goes on the form of a resource with a record behind it.');
        }

        $this->value = null;

        $lastSavedAt = $resource->getAttribute($resource->getUpdatedAtColumn());

        $this->withMeta([
            // Relative, so the request goes to whichever host the Office is
            // being read on rather than to whatever APP_URL happens to say.
            'restoreUrl' => route('office.unsaved-drafts.restore', [
                'resource' => Nova::resourceForModel($resource)::uriKey(),
                'resourceId' => $resource->getKey(),
            ], absolute: false),
            'lastSavedAt' => $lastSavedAt instanceof CarbonInterface ? $lastSavedAt->toIso8601String() : null,
        ]);
    }

    /**
     * Never write to the record: the field has no column, only a form.
     */
    public function fill(NovaRequest $request, $model): void
    {
        //
    }
}
