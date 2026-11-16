<?php

namespace App\Support\Widgets;

use App\Models\Festival;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

/**
 * What a gig widget can be for, as Backstage offers and receives it: a
 * venue, a promoter or a festival the account runs, written "venue:12",
 * "promoter:3" or "festival:5", or a publisher's own bookmarks.
 *
 * One list, so offering a choice, checking what came back and finding it
 * again cannot fall out of step as kinds are added.
 */
final class WidgetOwners
{
    /**
     * Each kind, with its model and what the dashboard calls it.
     *
     * @var array<string, array{model: class-string<Model>, label: string}>
     */
    private const KINDS = [
        'venue' => ['model' => Venue::class, 'label' => 'venue'],
        'promoter' => ['model' => Promoter::class, 'label' => 'promoter'],
        'festival' => ['model' => Festival::class, 'label' => 'festival'],
    ];

    /**
     * What an owner value has to look like.
     */
    public static function pattern(): string
    {
        return '/^(('.implode('|', array_keys(self::KINDS)).'):\d+|bookmarks)$/';
    }

    /**
     * What this account may make a widget for: its bookmarks when it is a
     * publisher, then each venue, promoter and festival it runs, by name.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function choicesFor(User $user): array
    {
        $runs = [
            'venue' => $user->venues()->orderBy('name')->get(['id', 'name']),
            'promoter' => $user->promoters()->orderBy('name')->get(['id', 'name']),
            'festival' => $user->managedFestivals()->orderBy('name')->get(['id', 'name']),
        ];

        $choices = $user->hasRole('is_publisher') ? [['value' => 'bookmarks', 'label' => 'My bookmarks']] : [];

        foreach ($runs as $kind => $owners) {
            foreach ($owners as $owner) {
                $choices[] = ['value' => $kind.':'.$owner->id, 'label' => $owner->name.' ('.self::KINDS[$kind]['label'].')'];
            }
        }

        return $choices;
    }

    /**
     * The owner an accepted value names. Whether the account may make a
     * widget for it is the policy's to say.
     */
    public static function resolve(string $owner, User $user): Model
    {
        if ($owner === 'bookmarks') {
            return $user;
        }

        [$kind, $id] = explode(':', $owner, 2);

        return self::KINDS[$kind]['model']::query()->findOrFail((int) $id);
    }
}
