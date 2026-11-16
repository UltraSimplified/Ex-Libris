<?php

namespace App\Nova\Fields;

use App\Nova\Feature;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * What an account is allowed to be, as the Office ticks it. Granted here and
 * nowhere else - the dashboard never submits one - so this is the only way
 * somebody becomes a writer, is trusted with venues or runs festivals.
 *
 * Staff hold most roles whether ticked or not, so those stay unticked on an
 * office account rather than being kept in step with the gate by hand.
 * Runs Festivals is the exception, and says so.
 *
 * A list of one kind of account (Writers, Promoters and the rest) makes new
 * accounts with its own role already ticked, and leaves the roles out of
 * its table.
 */
final class RoleFields
{
    public function __construct(
        private ?string $presetRole,
        private bool $onIndex,
    ) {}

    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return [
            $this->role('Venue manager', 'is_venue_mgr'),
            $this->role('Promoter', 'is_promoter'),
            /*
             * A writer made from a feature's writer field is a writer from
             * the start: that field only offers writers, so one left unticked
             * would vanish from it the moment it was saved.
             */
            $this->role('Writer', 'is_writer')
                ->default(fn (NovaRequest $request) => $request->viaResource === Feature::uriKey()
                    || $this->presetRole === 'is_writer'),
            // A friendly website, making gig widgets of its bookmarks.
            $this->role('Publisher', 'is_publisher'),
            /*
             * Managing its promoters' festivals from Backstage. Unlike the
             * roles above, staff do not hold this one unticked: it adds
             * festivals to their own Backstage, so it is theirs to tick.
             */
            $this->role('Runs Festivals', 'runs_festivals')
                ->help('Lets this account create and manage its promoters\' festivals in Backstage. Office accounts need it ticked too.'),
        ];
    }

    private function role(string $label, string $column): Boolean
    {
        return Boolean::make($label, $column)
            ->default(fn () => $this->presetRole === $column)
            ->showOnIndex($this->onIndex);
    }
}
