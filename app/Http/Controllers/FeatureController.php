<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Tag;
use App\Support\FeatureMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    /**
     * How many features each step of the Features page's infinite scroll
     * brings in: two rows of three.
     */
    public const MORE_PER_PAGE = 6;

    /**
     * The Features page: the newest four features that are not reviews,
     * leading, then every other feature on the site - reviews among them -
     * six at a time as the reader scrolls.
     *
     * A feature with no tag is left out of both, as it has always been left
     * out of the lead: its panel has no category to show.
     */
    public function index(): Response
    {
        return $this->featuresPage(
            'Features',
            leading: Feature::visible()->where('tag_id', '!=', 5),
            rest: Feature::visible()->whereNotNull('tag_id'),
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * The features filed under one tag - the page a feature's category
     * links to, as a listing's category links to its own - laid out as the
     * Features page is: the newest four leading, the rest as the reader
     * scrolls.
     */
    public function tag(string $slug): Response
    {
        $tag = Tag::findBySlug($slug) ?? abort(404);

        return $this->featuresPage(
            'Features / '.$tag->tag,
            leading: Feature::visible()->where('tag_id', $tag->id),
            rest: Feature::visible()->where('tag_id', $tag->id),
        );
    }

    /**
     * A page of features: the newest four that $leading allows, leading,
     * then those $rest allows that are not among them, six at a time as the
     * reader scrolls.
     *
     * @param  Builder<Feature>  $leading
     * @param  Builder<Feature>  $rest
     */
    private function featuresPage(string $title, Builder $leading, Builder $rest): Response
    {
        $lead = $leading
            ->orderByDesc('created_at')
            ->withByline()
            ->with('tag')
            ->limit(4)
            ->get();

        return Inertia::render('Magazine/Features', [
            'title' => $title,
            'latest' => $lead->first(),
            'features' => $lead->slice(1)->values(),
            'more' => Inertia::scroll(fn () => $rest
                ->whereKeyNot($lead->modelKeys())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->withByline()
                ->with('tag')
                ->paginate(self::MORE_PER_PAGE)),
        ]);
    }

    /**
     * Load a feature's listing only while the event is still to come or
     * under way, with the venue and category its row shows, as on the
     * listings pages. Once it has finished the feature reads as if it had
     * none, though the link stays in the Office.
     */
    private static function currentListing(BelongsTo $listing): void
    {
        $listing->current()->with(['venue', 'category']);
    }

    /**
     * A feature's page. A draft is not found, except by somebody who can
     * use the Office.
     */
    public function show($id, $slug)
    {
        $feature = Feature::visible()
            ->whereKey($id)
            ->withByline()
            ->with(['tag', 'listing' => self::currentListing(...)])
            ->firstOrFail();

        $feature['body'] = $feature->withAffiliateLinks(json_decode($feature['body']));

        return Inertia::render('Magazine/Feature', [
            'title' => $feature['title'],
            'feature' => $feature,
            'meta' => FeatureMeta::for($feature)->toArray(),
        ]);
    }

    /**
     * Show a feature to the office as the public will read it, on its own:
     * framed behind the eye on the Office's features list, without the
     * site's header, footer, advert or mailing list around it.
     */
    public function preview(Feature $feature): Response
    {
        $feature = Feature::whereKey($feature->getKey())
            ->withByline()
            ->with(['tag', 'listing' => self::currentListing(...)])
            ->firstOrFail();

        $feature['body'] = $feature->withAffiliateLinks(json_decode($feature['body']));

        return Inertia::render('Magazine/Feature', [
            'title' => $feature['title'],
            'feature' => $feature,
            'meta' => FeatureMeta::for($feature)->toArray(),
            'preview' => true,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Feature $feature)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Feature $feature)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Feature $feature)
    {
        //
    }
}
