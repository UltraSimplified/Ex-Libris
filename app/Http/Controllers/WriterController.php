<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Issue;
use App\Models\User;
use App\Support\WriterMeta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A writer's own page, reached from Our Writers: who they are, what they
 * have written for the magazine and the issues it was printed in, and the
 * writers either side of them on the masthead.
 */
class WriterController extends Controller
{
    /**
     * The columns of a feature its card shows.
     *
     * @var list<string>
     */
    private const CARD = ['id', 'slug', 'title', 'excerpt', 'cover_photo', 'cover_alt_text', 'published', 'tag_id', 'status'];

    /**
     * Show the writer whose name makes this slug.
     *
     * Writers are found by their name, as Our Writers links to them, so
     * every writer is read in masthead order - by first name - and the
     * writer found among them. An alumnus is found too: their bylines
     * still link here, though they are no longer on Our Writers.
     *
     * The previous and next writers are taken from the writers of the same
     * standing, so an active writer's are the names either side of theirs
     * on Our Writers, and an alumnus turns through the other alumni.
     */
    public function show(string $slug): Response
    {
        $everyWriter = User::writers()
            ->orderBy('name')
            ->get([...Feature::BYLINE, 'is_active_writer']);

        $writer = $everyWriter->first(fn (User $writer): bool => Str::slug($writer->name) === $slug);

        abort_if($writer === null, 404);

        $writers = $everyWriter
            ->where('is_active_writer', $writer->is_active_writer)
            ->values();

        $position = $writers->search(fn (User $candidate): bool => $candidate->is($writer));

        return Inertia::render('Writer', [
            'title' => $writer->name,
            'writer' => [...$this->byline($writer), 'bio' => $writer->bio],
            'features' => $writer->features()
                ->visible()
                ->with('tag')
                ->orderByDesc('published')
                ->orderByDesc('id')
                ->get(self::CARD),
            'issues' => $this->issues($writer),
            'previous' => $this->neighbour($writers, $position - 1),
            'next' => $this->neighbour($writers, $position + 1),
            'meta' => WriterMeta::for($writer)->toArray(),
        ]);
    }

    /**
     * The issues this writer has an article in, newest first, as covers
     * to download. The issue on sale now is shown but not offered, as the
     * archive does not give it away until the next is out; nor is one
     * with no PDF yet.
     *
     * @return list<array{id: int, issue_number: int, month_year: ?string, cover_photo: ?string, downloadable: bool}>
     */
    private function issues(User $writer): array
    {
        $onSaleNow = Issue::query()->max('issue_number');

        return $writer->issues()
            ->get(['issues.id', 'issue_number', 'month_year', 'cover_photo', 'pdf'])
            ->map(fn (Issue $issue): array => [
                'id' => $issue->id,
                'issue_number' => $issue->issue_number,
                'month_year' => $issue->month_year,
                'cover_photo' => $issue->cover_photo,
                'downloadable' => $issue->downloadable && $issue->issue_number !== $onSaleNow,
            ])
            ->all();
    }

    /**
     * The writer a step along the masthead, carried round from one end to
     * the other. Nobody when the writer is the only one.
     *
     * @param  Collection<int, User>  $writers
     * @return array{id: int, name: string, slug: string, avatar: ?string, avatar_alt_text: ?string}|null
     */
    private function neighbour(Collection $writers, int $position): ?array
    {
        $count = $writers->count();

        if ($count < 2) {
            return null;
        }

        return $this->byline($writers[($position % $count + $count) % $count]);
    }

    /**
     * A writer as a link to their page shows them: a name and a picture.
     *
     * @return array{id: int, name: string, slug: string, avatar: ?string, avatar_alt_text: ?string}
     */
    private function byline(User $writer): array
    {
        return [
            'id' => $writer->id,
            'name' => $writer->name,
            'slug' => Str::slug($writer->name),
            'avatar' => $writer->avatar,
            'avatar_alt_text' => $writer->avatar_alt_text,
        ];
    }
}
