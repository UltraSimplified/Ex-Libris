<?php

namespace App\Http\Requests\Shop;

use App\Services\Shop\Basket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddToBasketRequest extends FormRequest
{
    /**
     * Anyone may fill a basket.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Basket::MAX_PER_LINE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variant_id.required' => 'Choose a colour and size first.',
            'variant_id.exists' => 'That is no longer on sale.',
        ];
    }
}
