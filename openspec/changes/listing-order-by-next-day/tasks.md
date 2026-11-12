# Tasks

## 1. Ordering by the next day

- [x] 1.1 In `app/Models/Listing.php`, replace `scopeFinishingSoonest` with `scopeByNextDay(Builder $query, mixed $from = null)` as in design.md. It should:
  - select `listings.*` when the query has no columns yet
  - add the next-day expression as `next_day`, binding `$from` as `Y-m-d` and defaulting to today
  - order by `next_day`, then the last day, then `title`

  Add `tests/Feature/ListingsOrderTest.php`, with dates fixed through `$this->travelTo()`, covering the spec's order scenarios on the all listings page:
  - a show that opens in two days
  - an exhibition under way
  - between two pub quizzes
  - today's group
  - the pub quiz on a quiz night
  - same last day

  Each case asserts the `listings.N.id` order and the `next_day` values. Verify that `php artisan test tests/Feature/ListingsOrderTest.php --compact` passes.
- [x] 1.2 Switch every action in `app/Http/Controllers/ListingController.php` to `byNextDay()` with its first day, following design.md's table. Pass `currentDay`, `firstDay` and `dayHeadings` to the `Listings` page. Extend `ListingsOrderTest` with:
  - the chosen-range scenario (first day is the range's start)
  - `firstDay` for Tomorrow and Next week
  - `dayHeadings` false on Today, Tomorrow and a one-day range, and true on the all listings, This week, venue, promoter, category and bookmarked pages

  Verify that the test file passes, along with `tests/Feature/ListingDaysTest.php`, `tests/Feature/PromoterPageTest.php` and `tests/Feature/GuestBookmarkTest.php`.
- [x] 1.3 Switch the bookmarks panel in `app/Http/Controllers/Dashboard/HomeController.php` to `byNextDay()`. In `tests/Feature/DashboardBookmarksPanelTest.php`, add the "three saved events" scenario and check that `pivot.attending` still arrives. Rename the two "soonest finishing first" tests, there and in `tests/Feature/GuestBookmarkTest.php`, to describe the new order, keeping their assertions, which still hold. Verify that both files pass and that no `finishingSoonest` call remains (`grep -rn finishingSoonest app tests` is empty).
- [x] 1.4 Check the expression on MariaDB. Run the scope's SQL for the all listings page as a read-only query against staging, which holds a copy of live's listings. Verify that it runs, that the first rows follow the spec's rule (today's group by last day, then each later day), and that listings with specific days sit on their next chosen day. Write nothing to staging.

## 2. Day and month headings

- [x] 2.1 In `resources/js/lib/listingDates.ts`, add the heading key and label for a listing's `next_day`, given the server's `currentDay` and the page's `firstDay`, as in design.md:
  - a day key up to the last day of the month after `firstDay`'s month, a month key after that
  - `TODAY` / `TOMORROW` against `currentDay`
  - otherwise the weekday in full, the day and the month, with the short year when it is not this year
  - month headings in full, with the year when it is not this year

  Add `next_day` to the listing type in `resources/js/types/global.d.ts`. Verify that `npx vue-tsc --noEmit` and `npm run lint` are clean. The behaviour is verified in 2.3.
- [x] 2.2 In `resources/js/pages/Listings.vue` and `resources/js/components/GOP/ListingsIndex.vue`:
  - take the `currentDay`, `firstDay` and `dayHeadings` props
  - give `rows` a `heading` kind, built from `visibleListings` when `dayHeadings` is on and inserted before a listing whose key differs from the one before it
  - keep the advert count over listings only, so an advert still follows a listing
  - draw the heading as a `div` row holding an `h2`, in the page's existing strip style, the same on phone and desktop, and checked in light and dark

  Verify that `npm run build` succeeds and that the all listings page shows headings in the browser pane.
- [x] 2.3 Add `tests/Browser/ListingsHeadingsTest.php` covering the spec's heading scenarios:
  - the next three days
  - a day in the new year
  - January seen from November
  - a month of this year
  - a chosen range far ahead
  - the Today page
  - a one-day range
  - searching
  - emptying the last heart of a day

  The server's `currentDay` reaches the page as a prop. Check first that `$this->travelTo()` reaches the browser test's server, and if it does not, build the scenarios from dates relative to today. Verify that `php artisan test tests/Browser/ListingsHeadingsTest.php --compact` passes.
- [x] 2.4 Teach `tests/Browser/ListingsAdvertSlotsTest.php` the heading row: `LISTING_ROWS` maps it to "heading", and the expected rows now include the TODAY heading its listings sit under. Add the spec's "advert at the end of a day" scenario. Verify that the file passes, with every earlier assertion still made and none loosened.

## 3. Finishing

- [x] 3.1 Run `vendor/bin/pint --dirty --format agent` and `vendor/bin/sloppy diff main --fail-on=high`, then everything CI runs:
  - `composer test:lint`
  - `composer analyse`
  - `php artisan test tests/Feature tests/Unit --compact`
  - `npm run build`, then `php artisan test tests/Browser --compact`

  Verify that each passes, and report any failure with its output.

## Workflow follow-up

- The maintainer reviews, commits and pushes. A push to main deploys to staging and then live. There is no migration to run.
- Outside this change: weekly sessions entered as one long run without specific days sit under TODAY every day they run. Giving them specific days in the Dashboard moves each one to its real days.
- Archive the change once it is live and checked.
