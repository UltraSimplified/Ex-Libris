<?php

namespace App\Http\Requests\Shop;

use App\Services\Shop\Basket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBasketLineRequest extends FormRequest
{
    /**
     * Anyone may change their own basket, which lives in their session.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nought takes the line out.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Basket::MAX_PER_LINE],
        ];
    }
}
