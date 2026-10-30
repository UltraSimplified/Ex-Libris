<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a writer will find what they have written for the magazine.
 *
 * A stub. Features are written and edited in the Office, and nothing yet
 * lets a writer reach their own from here - so this holds the place in
 * the interface while that is worked out.
 *
 * Held to the writers: everybody else has no articles to list, and the
 * sidebar does not offer them the section at all.
 */
class ArticleController extends Controller
{
    /**
     * The kinds of thing the magazine publishes, and the tags a feature
     * is filed under for each. Arts and Music are two tags but one
     * section to write for, which is how the magazine has always run
     * them - so the mapping is held here rather than assumed to be one
     * tag apiece.
     *
     * @var array<string, array{label: string, tags: array<int, string>}>
     */
    public const SECTIONS = [
        'arts-music' => ['label' => 'Arts + Music', 'tags' => ['Arts', 'Music']],
        'interviews' => ['label' => 'Interviews', 'tags' => ['Interviews']],
        'reviews' => ['label' => 'Music Reviews', 'tags' => ['Reviews']], 'books' => ['label' => 'Books', 'tags' => ['Books']],
        'photojournals' => ['label' => 'Photojournals', 'tags' => ['Photojournals']],
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('is_writer'), 403);

        // Which of them is being written for, if the sidebar said. Anything
        // else names no section and is ignored rather than guessed at.
        $section = self::SECTIONS[$request->string('section')->toString()] ?? null;

        return Inertia::render('dashboard/articles/Index', [
            'section' => $section['label'] ?? null,
        ]);
    }
}
