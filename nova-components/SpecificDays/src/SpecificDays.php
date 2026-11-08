<?php

namespace Ultrasimplified\SpecificDays;

use DateTimeInterface;
use Laravel\Nova\Fields\Field;

/**
 * The days an event is on within its run, ticked on a calendar of every
 * day from its start date to its end date, with Select all, Clear all and
 * a chip for each weekday.
 *
 * The calendar follows the two date fields named here as they change on
 * the form, so it needs their values to start from, which it sends with
 * itself. Its value, both ways, is {"enabled": bool, "days": [...]} with
 * each day written "2026-11-03". What the days mean, and how they are
 * stored, is left to the resource through resolveUsing() and fillUsing().
 */
class SpecificDays extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'specific-days';

    /**
     * The date fields the calendar spans.
     */
    private string $startAttribute = 'start_date';

    private string $endAttribute = 'end_date';

    /**
     * Follow these two date fields rather than start_date and end_date.
     */
    public function between(string $startAttribute, string $endAttribute): static
    {
        $this->startAttribute = $startAttribute;
        $this->endAttribute = $endAttribute;

        return $this;
    }

    /**
     * Resolve the field's value, along with the dates the calendar starts
     * from.
     */
    public function resolve($resource, ?string $attribute = null): void
    {
        parent::resolve($resource, $attribute);

        $this->withMeta([
            'startAttribute' => $this->startAttribute,
            'endAttribute' => $this->endAttribute,
            'start' => self::day(data_get($resource, $this->startAttribute)),
            'end' => self::day(data_get($resource, $this->endAttribute)),
        ]);
    }

    /**
     * A date as "2026-11-03", or empty.
     */
    private static function day(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return substr((string) $date, 0, 10);
    }
}
