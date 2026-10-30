<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddressUpdateRequest extends FormRequest
{
    /**
     * Normalise what the browser posts before validation.
     *
     * A ticked checkbox arrives as the string "on", which the boolean rule
     * rejects - and this one decides whether half the form is required at
     * all, so reading it wrongly would ask for a delivery address nobody
     * meant to give.
     *
     * An emptied text input arrives as "", not as absent, so a number that
     * has been rubbed out would otherwise be stored as an empty string and
     * go on looking like a number the account has.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_same_as_billing' => filter_var(
                $this->input('delivery_same_as_billing'),
                FILTER_VALIDATE_BOOL
            ),
            'phone' => blank($this->input('phone')) ? null : trim((string) $this->input('phone')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The delivery fields are excluded rather than merely optional when the
     * two addresses are the same, so a half-filled delivery address left
     * over from before the box was ticked cannot be saved by accident.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_same_as_billing' => ['required', 'boolean'],

            // Kept on the account rather than on either address: it is how
            // the office reaches a person, not a property of a building.
            'phone' => ['nullable', 'string', 'max:30'],

            'billing.line_one' => ['required', 'string', 'max:255'],
            'billing.line_two' => ['nullable', 'string', 'max:255'],
            'billing.town' => ['required', 'string', 'max:255'],
            'billing.county' => ['nullable', 'string', 'max:255'],
            'billing.postcode' => ['required', 'string', 'max:20'],
            'billing.country' => ['required', 'string', 'max:255'],

            'delivery' => ['exclude_if:delivery_same_as_billing,true', 'required', 'array'],
            'delivery.line_one' => ['required', 'string', 'max:255'],
            'delivery.line_two' => ['nullable', 'string', 'max:255'],
            'delivery.town' => ['required', 'string', 'max:255'],
            'delivery.county' => ['nullable', 'string', 'max:255'],
            'delivery.postcode' => ['required', 'string', 'max:20'],
            'delivery.country' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'billing.line_one' => 'billing address',
            'billing.town' => 'billing town or city',
            'billing.postcode' => 'billing postcode',
            'billing.country' => 'billing country',
            'phone' => 'contact phone',
            'delivery.line_one' => 'delivery address',
            'delivery.town' => 'delivery town or city',
            'delivery.postcode' => 'delivery postcode',
            'delivery.country' => 'delivery country',
        ];
    }
}
