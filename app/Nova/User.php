<?php

namespace App\Nova;

use App\Models\Issue as IssueModel;
use App\Nova\Fields\ActiveWriterToggle;
use App\Nova\Filters\UserRole;
use App\Support\InitialsAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Facades\Storage;
use Jacobfitzp\NovaCanvas\Canvas;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Auth\PasswordValidationRules;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Tag;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * @extends \App\Nova\Resource<\App\Models\User>
 */
class User extends Resource
{
    use PasswordValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static $model = \App\Models\User::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name', 'email',
    ];

    /**
     * Whether the account may go without a login. A writer may: they are
     * credited on the magazine whether or not they ever sign in. A venue
     * manager or promoter works through the dashboard, so keeps one.
     */
    protected function loginIsOptional(NovaRequest $request): bool
    {
        return $request->boolean('is_writer')
            && ! $request->boolean('is_promoter')
            && ! $request->boolean('is_venue_mgr');
    }

    /**
     * The role a new account made here starts with ticked. None from Users
     * itself; the Writers, Venue managers and Promoters lists each tick
     * their own.
     */
    protected static function presetRole(): ?string
    {
        return null;
    }

    /**
     * Whether the table shows each account's roles. It does on Users,
     * where everybody is; a list of one kind of account goes without.
     */
    protected static function rolesOnIndex(): bool
    {
        return true;
    }

    /**
     * Whether the table shows which writers are active. Only on Writers:
     * everybody else on Users has nothing to show there.
     */
    protected static function activeWriterOnIndex(): bool
    {
        return false;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field|Panel|ResourceTool|MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [

            Avatar::make('Avatar', 'avatar')
                ->disk('do')
                ->path('avatars')
                ->prunable()
                ->nullable()
                ->preview(fn ($value, $disk) => $value
                    ? Storage::disk($disk)->url($value)
                    : InitialsAvatar::for($this->name)
                )
                ->thumbnail(fn ($value, $disk) => $value
                    ? Storage::disk($disk)->url($value)
                    : InitialsAvatar::for($this->name)
                ),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules(fn (NovaRequest $request) => $this->loginIsOptional($request)
                    ? ['nullable', 'email', 'max:254']
                    : ['required', 'email', 'max:254'])
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules(fn (NovaRequest $request) => $this->loginIsOptional($request)
                    ? $this->optionalPasswordRules()
                    : $this->passwordRules())
                ->updateRules($this->optionalPasswordRules()),

            /*
             * What this account is allowed to be. Granted here and nowhere
             * else - the dashboard never submits one - so this is the only
             * way somebody becomes a writer or is trusted with venues.
             *
             * Staff hold all three whether ticked or not, so these stay
             * unticked on an office account rather than being kept in step
             * with the gate by hand.
             */
            Boolean::make('Venue manager', 'is_venue_mgr')
                ->default(fn () => static::presetRole() === 'is_venue_mgr')
                ->showOnIndex(static::rolesOnIndex()),
            Boolean::make('Promoter', 'is_promoter')
                ->default(fn () => static::presetRole() === 'is_promoter')
                ->showOnIndex(static::rolesOnIndex()),
            /*
             * A writer made from a feature's writer field is a writer from
             * the start: that field only offers writers, so one left unticked
             * would vanish from it the moment it was saved.
             */
            Boolean::make('Writer', 'is_writer')
                ->default(fn (NovaRequest $request) => $request->viaResource === Feature::uriKey()
                    || static::presetRole() === 'is_writer')
                ->showOnIndex(static::rolesOnIndex()),

            /*
             * Whether a writer is still writing for the magazine. An active
             * writer is listed on Our Writers; one who is not becomes an
             * alumnus. Their page and the Writers list have a tick that
             * changes it there and then; the form has an ordinary one,
             * offered only once the account is ticked as a writer.
             */
            Boolean::make('Active writer', 'is_active_writer')
                ->default(true)
                ->onlyOnForms()
                ->dependsOn(['is_writer'], function (Boolean $field, NovaRequest $request, FormData $formData): void {
                    if (! $formData->boolean('is_writer')) {
                        $field->hide();
                    }
                }),
            ActiveWriterToggle::make('Active writer', 'is_active_writer')
                ->showOnIndex(static::activeWriterOnIndex())
                ->showOnDetail(fn () => (bool) $this->is_writer),

            /*
             * The byline. A writer used to be a record of their own and
             * this is what it carried - the note that runs under a piece,
             * and what the picture should be read as. Shown on the writers
             * so it stays out of the way of everybody else.
             */
            Canvas::make('Bio', 'bio')
                ->nullable()
                ->hideFromIndex()
                ->showOnDetail(fn () => (bool) $this->is_writer),

            Text::make('Avatar alt text', 'avatar_alt_text')
                ->nullable()
                ->hideFromIndex()
                ->showOnDetail(fn () => (bool) $this->is_writer),

            HasMany::make('Featured articles', 'features', Feature::class),

            /*
             * The issues a writer has an article in, listed on their page
             * on the site. Kept here by hand, since most of it was printed
             * rather than put online, and offered on writers alone.
             *
             * Picked on the form and saved with it, since a row opens the
             * form rather than the detail page. Saving puts the writer's
             * issues to exactly what the field holds. The field stays on
             * the detail page too: the picker finds it there to ask for
             * its issues, newest first as the Office lists them.
             */
            Tag::make('Issues', 'issues', Issue::class)
                ->displayAsList()
                ->preload()
                ->dontReorderAttachables()
                ->hideFromIndex()
                // A picker asks with no user loaded, so only a saved user is judged here;
                // authorizedToAttach() keeps everybody but a writer out regardless.
                ->canSee(fn (): bool => ! $this->resource?->exists || (bool) $this->resource->is_writer)
                ->dependsOn(['is_writer'], function (Tag $field, NovaRequest $request, FormData $formData): void {
                    if (! $formData->boolean('is_writer')) {
                        $field->hide();
                    }
                }),
        ];
    }

    /**
     * Issues are kept for the writers alone, so nobody else is offered one,
     * however the request to attach it is made.
     *
     * @param  Model  $model
     */
    public function authorizedToAttachAny(NovaRequest $request, $model, bool $invertible = false): bool
    {
        if ($model instanceof IssueModel && ! $this->resource->is_writer) {
            return false;
        }

        return parent::authorizedToAttachAny($request, $model, $invertible);
    }

    /**
     * @param  Model  $model
     */
    public function authorizedToAttach(NovaRequest $request, $model, bool $invertible = false): bool
    {
        if ($model instanceof IssueModel && ! $this->resource->is_writer) {
            return false;
        }

        return parent::authorizedToAttach($request, $model, $invertible);
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new UserRole,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
