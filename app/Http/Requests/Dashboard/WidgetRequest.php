<?php

namespace App\Http\Requests\Dashboard;

use App\Support\Brand;
use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetFont;
use App\Support\Widgets\WidgetOptions;
use App\Support\Widgets\WidgetSizeMode;
use App\Support\Widgets\WidgetTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WidgetRequest extends FormRequest
{
    /**
     * The controller authorises against the policy, and against the venue,
     * promoter or bookmarks a new widget is for.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ticked checkboxes arrive as "on" or "1", unticked ones as "0" from the
     * hidden field beside them, and an empty colour field posts the empty
     * string. All three are put into
     * the shape the rules expect.
     */
    protected function prepareForValidation(): void
    {
        $options = (array) $this->input('options', []);

        // Only what was posted: the new-widget form sends none of these,
        // and a widget starts with them on, not off.
        foreach (['show_images', 'show_price', 'show_venue'] as $flag) {
            if (array_key_exists($flag, $options)) {
                $options[$flag] = filter_var($options[$flag], FILTER_VALIDATE_BOOL);
            }
        }

        $colours = collect((array) $this->input('colours', []))
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();

        $this->merge([
            'options' => $options,
            'colours' => $colours,
            'active' => filter_var($this->input('active', $this->isMethod('post')), FILTER_VALIDATE_BOOL),
            'use_brand' => filter_var($this->input('use_brand', true), FILTER_VALIDATE_BOOL),
            'width' => $this->input('width') === '' ? null : $this->input('width'),
            'height' => $this->input('height') === '' ? null : $this->input('height'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'owner' => [Rule::requiredIf($this->isMethod('post')), 'string', 'regex:/^((venue|promoter):\d+|bookmarks)$/'],
            'name' => ['required', 'string', 'max:100'],
            'template' => ['required', Rule::enum(WidgetTemplate::class)],
            'density' => ['required', Rule::enum(WidgetDensity::class)],
            'font' => ['sometimes', Rule::enum(WidgetFont::class)],
            'size_mode' => ['sometimes', Rule::enum(WidgetSizeMode::class)],
            'width' => ['nullable', 'integer', 'min:200', 'max:2400'],
            'height' => ['nullable', 'integer', 'min:100', 'max:4000', Rule::requiredIf($this->input('size_mode') === WidgetSizeMode::Fixed->value)],
            'active' => ['boolean'],
            'options' => ['array'],
            'options.count' => ['sometimes', 'integer', 'min:1', 'max:'.WidgetOptions::MAX_COUNT],
            'options.show_images' => ['boolean'],
            'options.show_price' => ['boolean'],
            'options.show_venue' => ['boolean'],
            'options.button_text' => ['nullable', 'string', 'max:30'],
            'use_brand' => ['boolean'],
            'colours' => ['array'],
            ...collect(Brand::COLUMNS)
                ->mapWithKeys(fn (string $column): array => ['colours.'.$column => ['nullable', 'hex_color']])
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'owner' => 'venue or promoter',
            'options.count' => 'number of events',
            'options.button_text' => 'button wording',
            'colours.brand_colour' => 'brand colour',
            'colours.accent_colour1' => 'accent colour',
            'colours.accent_colour2' => 'second accent colour',
            'colours.brand_background_colour' => 'background colour',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'height.required' => 'A fixed-size widget needs a height.',
        ];
    }

    /**
     * The fields to save on the widget, settled: options with their
     * defaults filled in, and colours of its own only when it is not
     * following its owner's.
     *
     * @return array<string, mixed>
     */
    public function widgetAttributes(): array
    {
        $validated = $this->safe()->except(['owner', 'use_brand', 'colours', 'options']);

        return [
            ...$validated,
            'options' => WidgetOptions::from($this->validated('options', []))->toArray(),
            'colours' => $this->validated('use_brand')
                ? null
                : collect(Brand::COLUMNS)->mapWithKeys(fn (string $column): array => [
                    $column => $this->validated('colours.'.$column),
                ])->all(),
        ];
    }
}
