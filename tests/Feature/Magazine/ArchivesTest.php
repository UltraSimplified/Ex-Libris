<?php

use App\Models\Issue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

test('the archive defaults to the current year', function () {
    Issue::factory()->forYear((int) date('Y'))->numbered(30)->create();

    $this->get(route('Archives'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->where('year', date('Y'))
        );
});

test('the current year archive omits the newest issue', function () {
    $year = (int) date('Y');

    Issue::factory()->forYear($year)->numbered(30)->create();
    Issue::factory()->forYear($year)->numbered(29)->create();
    Issue::factory()->forYear($year)->numbered(28)->create();

    // The current issue is shown elsewhere on the site, so archives slices it off.
    $this->get(route('Archives'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->has('issues', 2)
            ->where('issues.1.issue_number', 29)
            ->where('issues.2.issue_number', 28)
        );
});

test('a past year archive returns every issue from that year', function () {
    Issue::factory()->forYear(2024)->numbered(12)->create();
    Issue::factory()->forYear(2024)->numbered(11)->create();

    // Unlike the current year, past years are not sliced.
    $this->get(route('Archive Year', ['year' => 2024]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->where('year', '2024')
            ->has('issues', 2)
        );
});

test('issues from other years are excluded', function () {
    Issue::factory()->forYear(2024)->numbered(12)->create();
    Issue::factory()->forYear(2023)->numbered(5)->create();

    $this->get(route('Archive Year', ['year' => 2024]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->has('issues', 1)
            ->where('issues.0.issue_number', 12)
        );
});

test('issues are ordered by issue number descending', function () {
    Issue::factory()->forYear(2024)->numbered(9)->create();
    Issue::factory()->forYear(2024)->numbered(11)->create();
    Issue::factory()->forYear(2024)->numbered(10)->create();

    $this->get(route('Archive Year', ['year' => 2024]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->where('issues.0.issue_number', 11)
            ->where('issues.1.issue_number', 10)
            ->where('issues.2.issue_number', 9)
        );
});

test('each issue says whether it has a file to download', function () {
    Issue::factory()->forYear(2018)->numbered(42)->create(['pdf' => null]);
    Issue::factory()->forYear(2018)->numbered(41)->create();

    $this->get(route('Archive Year', ['year' => 2018]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->where('issues.0.issue_number', 42)
            ->where('issues.0.downloadable', false)
            ->where('issues.1.issue_number', 41)
            ->where('issues.1.downloadable', true)
        );
});

test('a year with no issues renders an empty archive', function () {
    $this->get(route('Archive Year', ['year' => 1999]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Archives')
            ->has('issues', 0)
        );
});
