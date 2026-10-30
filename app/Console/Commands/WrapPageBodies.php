<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wraps a page's raw HTML body in a single "Page body" layout so Nova can
 * edit it.
 *
 * The Flexible field cannot parse raw HTML: it renders no layouts, and
 * saving the page writes back the empty set it was given, silently
 * discarding whatever was there. Wrapping the HTML first makes those pages
 * safe to open.
 *
 * Runs as a dry run unless --commit is passed, because the configured
 * database is the live one. Re-running is safe: a body that already decodes
 * to a layout set is left alone.
 */
class WrapPageBodies extends Command
{
    protected $signature = 'pages:wrap-bodies
        {--commit : Actually write to the database}
        {--slug=* : Limit to these slugs}';

    protected $description = 'Wrap raw HTML page bodies in a Flexible "Page body" layout';

    /**
     * The layout handle registered for "Page body" in App\Nova\Page.
     */
    private const LAYOUT = 'copy';

    public function handle(): int
    {
        $pages = Page::query()
            ->when($this->option('slug'), fn ($query, array $slugs) => $query->whereIn('slug', $slugs))
            ->orderBy('slug')
            ->get();

        $wrap = $pages->filter(fn (Page $page): bool => $this->isRawHtml($page->body));

        foreach ($pages as $page) {
            $this->line(sprintf(
                '%-14s %s',
                $page->slug,
                $wrap->contains($page)
                    ? 'wrap '.strlen((string) $page->body).' characters of HTML'
                    : '<fg=gray>already laid out, leaving alone</>'
            ));
        }

        if ($wrap->isEmpty()) {
            $this->newLine();
            $this->info('Nothing to wrap.');

            return self::SUCCESS;
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->warn($wrap->count().' page(s) would be wrapped. Re-run with --commit to write.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($wrap): void {
            foreach ($wrap as $page) {
                $page->body = $this->wrapped((string) $page->body);
                $page->save();
            }
        });

        $this->newLine();
        $this->info('Wrapped '.$wrap->count().' page(s).');

        return self::SUCCESS;
    }

    /**
     * Whether this body is loose HTML rather than a saved layout set.
     *
     * A Flexible body is a JSON array of layouts; anything that does not
     * decode to an array is the raw copy we are here for.
     */
    private function isRawHtml(mixed $body): bool
    {
        return filled($body) && ! is_array(json_decode((string) $body, true));
    }

    /**
     * The same shape Nova writes: one layout, a random key, the HTML in the
     * layout's only field.
     */
    private function wrapped(string $html): string
    {
        return json_encode([[
            'layout' => self::LAYOUT,
            'key' => 'c'.substr(bin2hex(random_bytes(8)), 0, 15),
            'attributes' => ['text' => $html],
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
