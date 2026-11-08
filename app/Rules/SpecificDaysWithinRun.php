<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The specific days the Office's calendar sends, as one JSON value:
 * {"enabled": true, "days": ["2026-11-03", …]}. While "Set specific days"
 * is ticked there must be at least one day, an end date, and every day a
 * real date between the start and end.
 *
 * The Dashboard checks the same things with ordinary array rules, and
 * words them with these same messages, so the two forms say the same.
 */
final class SpecificDaysWithinRun implements DataAwareRule, ValidationRule
{
    public const CHOOSE_A_DAY = 'Choose at least one day, or untick "Set specific days".';

    public const NEEDS_AN_END_DATE = 'Add an end date to choose specific days.';

    public const NOT_A_DATE = 'One of the chosen days is not a date.';

    public const OUTSIDE_THE_RUN = 'Each chosen day must fall between the start and end dates.';

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * What the calendar sent, as whether the box is ticked and the days
     * ticked. Anything that is not the calendar's JSON counts as unticked.
     *
     * @return array{enabled: bool, days: list<mixed>}
     */
    public static function chosen(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded) || ! ($decoded['enabled'] ?? false)) {
            return ['enabled' => false, 'days' => []];
        }

        return ['enabled' => true, 'days' => array_values((array) ($decoded['days'] ?? []))];
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        ['enabled' => $enabled, 'days' => $days] = self::chosen($value);

        if (! $enabled) {
            return;
        }

        $message = $this->problemWith($days);

        if ($message !== null) {
            $fail($message);
        }
    }

    /**
     * The first thing wrong with the days, in the order a person would put
     * it right: choose a day, give an end date, then fix the days.
     *
     * @param  list<mixed>  $days
     */
    private function problemWith(array $days): ?string
    {
        if ($days === []) {
            return self::CHOOSE_A_DAY;
        }

        if (blank($this->data['end_date'] ?? null)) {
            return self::NEEDS_AN_END_DATE;
        }

        foreach ($days as $day) {
            if (! self::isDay($day)) {
                return self::NOT_A_DATE;
            }
        }

        $firstDay = Carbon::parse($this->data['start_date'])->toDateString();
        $lastDay = Carbon::parse($this->data['end_date'])->toDateString();

        foreach ($days as $day) {
            if ($day < $firstDay || $day > $lastDay) {
                return self::OUTSIDE_THE_RUN;
            }
        }

        return null;
    }

    /**
     * Whether a value is a real day written as "2026-11-03": the 31st of
     * June is not.
     */
    private static function isDay(mixed $day): bool
    {
        if (! is_string($day)) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return $parsed !== false && $parsed->format('Y-m-d') === $day;
    }
}
