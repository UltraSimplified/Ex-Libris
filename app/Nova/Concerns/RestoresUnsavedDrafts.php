<?php

namespace App\Nova\Concerns;

use App\Support\UnsavedDrafts;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Opens a resource's form with the unsaved changes its editor asked to have
 * back, when there are some waiting: see UnsavedDrafts.
 *
 * The record is filled in memory, by the same fields that would fill it on
 * Update, and the form drawn from it. The callbacks a fill leaves for after
 * a save, which store pictures, are dropped with it.
 */
trait RestoresUnsavedDrafts
{
    public function creationFieldsWithinPanels(NovaRequest $request): FieldCollection
    {
        $draft = UnsavedDrafts::pull($request, static::uriKey(), null);

        if ($draft !== null) {
            static::fill($draft, $this->resource);
            self::holdAsStored($this->resource);
        }

        return parent::creationFieldsWithinPanels($request);
    }

    public function updateFieldsWithinPanels(NovaRequest $request, ?Resource $resource = null): FieldCollection
    {
        $draft = UnsavedDrafts::pull($request, static::uriKey(), $this->resource->getKey());

        if ($draft !== null) {
            static::fillForUpdate($draft, $this->resource);
            self::holdAsStored($this->resource);
        }

        return parent::updateFieldsWithinPanels($request, $resource);
    }

    /**
     * Leave the record holding the changes as it holds them once saved and
     * read back: a feature's body as the JSON it is stored as, rather than
     * the collection the Flexible field fills it with, which the rest of the
     * form does not expect. The listings a body can show are worked out
     * from that JSON, for one.
     */
    private static function holdAsStored(Model $model): void
    {
        foreach ($model->getDirty() as $attribute => $value) {
            if ($value instanceof Jsonable && ! $model->hasCast($attribute)) {
                $model->setAttribute($attribute, $value->toJson());
            }
        }
    }
}
