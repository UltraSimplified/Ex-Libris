<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Nova\Resource;

/**
 * A Nova resource whose $title names something the model has not got shows
 * blank rows wherever it is picked from - relationship dropdowns, search
 * results, breadcrumbs - with no error to explain it. The Promoter resource
 * pointed at a "promoter" column that does not exist, so its picker on the
 * listing form was a list of empty options.
 *
 * $search is the same mistake with a louder failure: Feature listed "tag",
 * which lives on the related row, and every search of the resource died on
 * an unknown column.
 */

/**
 * Every concrete Nova resource, paired with a bare instance of its model.
 *
 * @return array<int, array{0: string, 1: Model}>
 */
function novaResources(): array
{
    return collect(glob(app_path('Nova/*.php')))
        ->map(fn (string $file): string => 'App\\Nova\\'.basename($file, '.php'))
        ->filter(fn (string $resource): bool => is_subclass_of($resource, Laravel\Nova\Resource::class)
            && ! (new ReflectionClass($resource))->isAbstract()
            && filled($resource::$model ?? null))
        ->map(fn (string $resource): array => [$resource, new ($resource::$model)])
        ->values()
        ->all();
}

/**
 * Whether the model can actually produce the named attribute.
 *
 * Nova's search accepts "relation.column", which it resolves against the
 * related table, so a dotted name is followed rather than looked for on the
 * model's own table.
 */
function modelHas(Model $model, string $attribute): bool
{
    if (str_contains($attribute, '.')) {
        [$relation, $column] = explode('.', $attribute, 2);

        return method_exists($model, $relation)
            && modelHas($model->{$relation}()->getRelated(), $column);
    }

    return Schema::hasColumn($model->getTable(), $attribute)
        || $model->hasGetMutator($attribute)
        || $model->hasAttributeMutator($attribute)
        || method_exists($model, 'get'.Str::studly($attribute).'Attribute');
}

test('every resource title resolves to something the model actually has', function () {
    $missing = collect(novaResources())
        ->reject(fn (array $pair): bool => modelHas($pair[1], $pair[0]::$title))
        ->map(fn (array $pair): string => class_basename($pair[0]).'::$title = '.$pair[0]::$title)
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('every searchable column exists on its own table', function () {
    $missing = collect(novaResources())
        ->flatMap(fn (array $pair): array => collect($pair[0]::$search)
            ->reject(fn (string $column): bool => modelHas($pair[1], $column))
            ->map(fn (string $column): string => class_basename($pair[0]).'::$search = '.$column)
            ->all())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
