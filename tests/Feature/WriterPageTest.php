<?php

use App\Models\Feature;
use App\Models\Issue;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * Three writers on the masthead, which is set by first name: Adam
 * Horovitz, Judith Gunn, Paul Harper.
 *
 * @return array{gunn: User, harper: User, horovitz: User}
 */
function mastheadOfThree(): array
{
    return [
        'harper' => User::factory()->writer()->create(['name' => 'Paul Harper', 'bio' => 'Writes about music.']),
        'horovitz' => User::factory()->writer()->create(['name' => 'Adam Horovitz']),
        'gunn' => User::factory()->writer()->create(['name' => 'Judith Gunn']),
    ];
}

test('a writer\'s page shows them and what they have written, newest first', function () {
    ['harper' => $harper, 'gunn' => $gunn] = mastheadOfThree();
    $tag = Tag::factory()->create(['tag' => 'Interviews']);
    $older = Feature::factory()->for($harper)->create(['title' => 'Older', 'published' => '2026-03-01', 'tag_id' => $tag->id]);
    $newer = Feature::factory()->for($harper)->create(['title' => 'Newer', 'published' => '2026-09-01', 'tag_id' => $tag->id]);
    Feature::factory()->for($gunn)->create(['title' => 'Not Harper\'s']);

    $this->get('/our-writers/paul-harper')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Writer')
            ->where('writer.name', 'Paul Harper')
            ->where('writer.slug', 'paul-harper')
            ->where('writer.bio', 'Writes about music.')
            ->where('writer.avatar', $harper->avatar)
            ->has('features', 2)
            ->where('features.0.id', $newer->id)
            ->where('features.0.tag.tag', 'Interviews')
            ->where('features.1.id', $older->id)
        );
});

test('the previous and next writers are their neighbours on the masthead', function () {
    mastheadOfThree();

    $this->get('/our-writers/paul-harper')
        ->assertInertia(fn ($page) => $page
            ->where('previous.name', 'Judith Gunn')
            ->where('previous.slug', 'judith-gunn')
            ->where('next.name', 'Adam Horovitz')
            ->where('next.slug', 'adam-horovitz')
        );
});

test('the masthead carries round from one end to the other', function () {
    mastheadOfThree();

    $this->get('/our-writers/judith-gunn')
        ->assertInertia(fn ($page) => $page
            ->where('previous.name', 'Adam Horovitz')
            ->where('next.name', 'Paul Harper')
        );

    $this->get('/our-writers/adam-horovitz')
        ->assertInertia(fn ($page) => $page
            ->where('previous.name', 'Paul Harper')
            ->where('next.name', 'Judith Gunn')
        );
});

/**
 * Previous and next are the names either side on Our Writers, which is
 * set by first name. By surname Mia Brown would sit between Zoe Adams and
 * Amy Young instead.
 */
test('the writers either side are the names either side on Our Writers', function () {
    User::factory()->writer()->create(['name' => 'Zoe Adams']);
    User::factory()->writer()->create(['name' => 'Mia Brown']);
    User::factory()->writer()->create(['name' => 'Amy Young']);

    $this->get('/our-writers/mia-brown')
        ->assertInertia(fn ($page) => $page
            ->where('previous.name', 'Amy Young')
            ->where('next.name', 'Zoe Adams')
        );

    $this->get('/our-writers/zoe-adams')
        ->assertInertia(fn ($page) => $page
            ->where('previous.name', 'Mia Brown')
            ->where('next.name', 'Amy Young')
        );
});

test('a writer on their own has nobody either side', function () {
    User::factory()->writer()->create(['name' => 'Paul Harper']);

    $this->get('/our-writers/paul-harper')
        ->assertInertia(fn ($page) => $page
            ->where('previous', null)
            ->where('next', null)
        );
});

test('a name with an accent is found by its plain slug', function () {
    User::factory()->writer()->create(['name' => 'Siân Magill']);

    $this->get('/our-writers/sian-magill')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('writer.name', 'Siân Magill'));
});

test('only the writers have a page', function () {
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    User::factory()->create(['name' => 'Alex Hobbis', 'is_writer' => false]);

    $this->get('/our-writers/alex-hobbis')->assertNotFound();
    $this->get('/our-writers/nobody-at-all')->assertNotFound();
});

/**
 * The account behind a writer may hold an email address and a telephone
 * number, and neither belongs on a public page.
 */
test('a writer\'s page carries no contact details', function () {
    User::factory()->writer()->create([
        'name' => 'Paul Harper',
        'email' => 'paul@example.com',
        'phone' => '01453 000000',
    ]);

    expect($this->get('/our-writers/paul-harper')->assertOk()->getContent())
        ->toContain('Paul Harper')
        ->not->toContain('paul@example.com')
        ->not->toContain('01453 000000');
});

test('a writer\'s page tells crawlers who it is about', function () {
    $harper = mastheadOfThree()['harper'];

    $response = $this->get('/our-writers/paul-harper')
        ->assertSee('<meta property="og:type" content="profile">', false)
        ->assertSee('<meta property="og:title" content="Paul Harper">', false)
        ->assertSee('<link rel="canonical" href="'.route('writer', 'paul-harper').'">', false);

    expect($response->viewData('page')['props']['meta'])->toMatchArray([
        'description' => 'Writes about music.',
        'image' => Storage::disk('do')->url($harper->avatar),
    ])->and($response->viewData('page')['props']['meta']['jsonLd'])->toMatchArray([
        '@type' => 'ProfilePage',
        'mainEntity' => [
            '@type' => 'Person',
            'name' => 'Paul Harper',
            'description' => 'Writes about music.',
            'image' => Storage::disk('do')->url($harper->avatar),
        ],
    ]);
});

test('a writer with no note is described by what they write for', function () {
    User::factory()->writer()->create(['name' => 'Adam Horovitz', 'bio' => null]);

    expect($this->get('/our-writers/adam-horovitz')->viewData('page')['props']['meta']['description'])
        ->toBe('Articles by Adam Horovitz for Good on Paper.');
});

/**
 * Issues 53 and 63 with an article by the writer, 58 without, and 70 on
 * sale now - the highest numbered, which the archive does not give away.
 *
 * @return array{writer: User, issues: array<int, Issue>}
 */
function writerInPrint(): array
{
    $writer = User::factory()->writer()->create(['name' => 'John Seagrave']);
    $issues = collect([53, 58, 63, 70])
        ->mapWithKeys(fn (int $number): array => [$number => Issue::factory()->create(['issue_number' => $number, 'month_year' => "Issue month {$number}"])])
        ->all();

    $writer->issues()->attach([$issues[53]->id, $issues[63]->id, $issues[70]->id]);

    return ['writer' => $writer, 'issues' => $issues];
}

test('a writer\'s page lists the issues they have an article in, newest first', function () {
    ['issues' => $issues] = writerInPrint();

    $this->get('/our-writers/john-seagrave')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 3)
            ->where('issues.1', [
                'id' => $issues[63]->id,
                'issue_number' => 63,
                'month_year' => 'Issue month 63',
                'cover_photo' => $issues[63]->cover_photo,
                'downloadable' => true,
            ])
            ->where('issues.0.issue_number', 70)
            ->where('issues.2.issue_number', 53)
        );
});

test('the issue on sale now is shown but not offered to download', function () {
    writerInPrint();

    $this->get('/our-writers/john-seagrave')
        ->assertInertia(fn ($page) => $page
            ->where('issues.0.issue_number', 70)
            ->where('issues.0.downloadable', false)
        );
});

test('an issue with no PDF yet is shown but not offered to download', function () {
    ['issues' => $issues] = writerInPrint();
    $issues[63]->forceFill(['pdf' => null])->save();

    $this->get('/our-writers/john-seagrave')
        ->assertInertia(fn ($page) => $page
            ->where('issues.1.issue_number', 63)
            ->where('issues.1.downloadable', false)
            ->where('issues.2.downloadable', true)
        );
});

test('a writer with no issues linked has none to show', function () {
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    Issue::factory()->create(['issue_number' => 70]);

    $this->get('/our-writers/paul-harper')
        ->assertInertia(fn ($page) => $page->where('issues', []));
});
