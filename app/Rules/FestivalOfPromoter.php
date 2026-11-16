<?php

namespace App\Rules;

use App\Models\Festival;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * A listing's festival is one of its own promoter's, and one whoever saves
 * it may choose.
 *
 * Backstage hands in the festivals the account manages, and the festival
 * the listing already has, which stays valid for whoever saves it - a
 * venue owner editing the time of a festival event in their building.
 * The Office hands in neither: staff may choose any festival, so long as
 * it is the listing's promoter's.
 */
final class FestivalOfPromoter implements DataAwareRule, ValidationRule
{
    public const NEEDS_A_PROMOTER = 'Choose the promoter putting it on before choosing a festival.';

    public const ANOTHER_PROMOTERS = 'That festival is put on by another promoter. Choose one of this promoter\'s festivals.';

    public const NOT_YOURS = 'You can only put a listing in a festival you run.';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  Builder<Festival>|null  $choosable  the festivals the saver may choose, or null for any
     * @param  string  $promoterField  where the promoter's id arrives: promoter_id from Backstage, promoter from the Office
     */
    public function __construct(
        private ?Builder $choosable = null,
        private ?int $kept = null,
        private string $promoterField = 'promoter_id',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $promoterId = Arr::get($this->data, $this->promoterField);

        if (blank($promoterId)) {
            $fail(self::NEEDS_A_PROMOTER);

            return;
        }

        $festival = Festival::query()->find($value, ['id', 'promoter_id']);

        if ($festival === null || (int) $festival->promoter_id !== (int) $promoterId) {
            $fail(self::ANOTHER_PROMOTERS);

            return;
        }

        if (! $this->mayChoose($festival)) {
            $fail(self::NOT_YOURS);
        }
    }

    private function mayChoose(Festival $festival): bool
    {
        if ($this->choosable === null || (int) $festival->id === $this->kept) {
            return true;
        }

        return (clone $this->choosable)->whereKey($festival->id)->exists();
    }
}
