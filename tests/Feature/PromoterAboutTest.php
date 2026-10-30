<?php

use App\Models\Promoter;
use App\Nova\Promoter as NovaPromoter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Whether the Office's promoter form accepts this about.
 */
function novaPromoterAboutPasses(?string $about): bool
{
    $request = NovaRequest::create('/nova-api/promoters', 'POST', ['about' => $about]);
    $field = collect((new NovaPromoter(new Promoter))->fields($request))
        ->first(fn ($field): bool => $field instanceof Field && $field->attribute === 'about');

    return Validator::make(['about' => $about], $field->getCreationRules($request))->passes();
}

/*
 * A promoter's about was a 255-character string column, so a paragraph or
 * two failed to save from the Office and the dashboard alike.
 */
test('a promoter\'s about is held in a column with room for a few paragraphs', function () {
    expect(Schema::getColumnType('promoters', 'about'))->toBe('text');
});

test('the Office takes an about of up to 2,000 characters, as the dashboard does', function () {
    expect(novaPromoterAboutPasses(str_repeat('a', 600)))->toBeTrue()
        ->and(novaPromoterAboutPasses(str_repeat('a', 2000)))->toBeTrue()
        ->and(novaPromoterAboutPasses(null))->toBeTrue()
        ->and(novaPromoterAboutPasses(str_repeat('a', 2001)))->toBeFalse();
});
