<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\ValidatesBrandProfile;
use App\Models\Festival;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FestivalRequest extends FormRequest
{
    use ValidatesBrandProfile {
        messages as brandProfileMessages;
    }

    /**
     * The controller authorises against the policy, which needs the festival
     * itself to answer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A festival's details as a promoter's are, and on a new one the
     * promoter putting it on, one this account runs, and any earlier
     * festival of its own it was started from. Once made, a festival stays
     * with its promoter, so an edit does not ask.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                ...$this->brandProfileRules(),
                'promoter_id' => [
                    'required',
                    'integer',
                    Rule::exists('promoters', 'id')->where('user_id', $this->user()->id),
                ],
                'from' => [
                    'nullable',
                    'integer',
                    Rule::in($this->user()->managedFestivals()->pluck('festivals.id')),
                ],
            ];
        }

        return $this->brandProfileRules();
    }

    /**
     * The earlier festival a new one was started from, once validated.
     */
    public function earlierFestival(): ?Festival
    {
        return $this->filled('from') ? Festival::find($this->integer('from')) : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->brandProfileMessages(),
            'promoter_id.exists' => 'You can only put on a festival for a promoter you run.',
            'from.in' => 'You can only start from a festival you run.',
        ];
    }
}
