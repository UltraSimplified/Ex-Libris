<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\ValidatesBrandProfile;
use Illuminate\Foundation\Http\FormRequest;

class PromoterRequest extends FormRequest
{
    use ValidatesBrandProfile;

    /**
     * The controller authorises against the policy, which needs the promoter
     * itself to answer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->brandProfileRules();
    }
}
