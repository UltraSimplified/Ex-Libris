<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\User;
use App\Support\SitePageMeta;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Pages that need their own component because their content is not just
     * a body: the writers page is a generated list, not something anyone
     * edits in Nova.
     *
     * @var array<string, string>
     */
    private const COMPONENTS = [
        'our-writers' => 'Our-writers',
    ];

    /**
     * Show whichever page owns this slug.
     *
     * Missing slugs raise a 404, which the exception handler renders as the
     * Page Not Found screen.
     */
    public function show(string $slug): Response
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return Inertia::render(self::COMPONENTS[$slug] ?? 'Page', [
            'title' => $page->title,
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $this->body($page),
            ],
            'meta' => SitePageMeta::for($page)->toArray(),
            ...$this->extraProps($slug),
        ]);
    }

    /**
     * A page body is either Flexible Content laid out in Nova or, on the
     * older pages, a lump of HTML. Decoding tells the two apart, and the
     * template renders whichever it is given.
     *
     * @return array<int, mixed>|string
     */
    private function body(Page $page): array|string
    {
        $decoded = json_decode((string) $page->body, true);

        return is_array($decoded) ? $decoded : (string) $page->body;
    }

    /**
     * Anything a particular page needs beyond its own record.
     *
     * @return array<string, mixed>
     */
    private function extraProps(string $slug): array
    {
        if ($slug !== 'our-writers') {
            return [];
        }

        return [
            'writers' => User::masthead()
                ->get()
                ->map(fn (User $writer): array => [
                    'id' => $writer->id,
                    'name' => $writer->name,
                    'slug' => Str::slug($writer->name, '-'),
                ])
                ->all(),
        ];
    }
}
