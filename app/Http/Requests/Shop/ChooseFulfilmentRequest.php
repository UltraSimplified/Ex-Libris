<?php

namespace App\Http\Requests\Shop;

use App\Support\Shop\Fulfilment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChooseFulfilmentRequest extends FormRequest
{
    /**
     * Anyone may choose how their own basket reaches them.
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
            'fulfilment' => ['required', Rule::enum(Fulfilment::class)],
        ];
    }
}
