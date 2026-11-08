# Tasks

## 1. Data model

- [x] 1.1 Create the `listing_days` migration with `php artisan make:migration`: `listing_id` constrained with cascade on delete, `day` as `date`, a unique index on (`listing_id`, `day`), an index on `day`, and a `down()` that drops the table. Verify that the feature suite builds its in-memory SQLite schema with it.
- [x] 1.2 Add a `ListingDay` model (no timestamps, `day` cast `date:Y-m-d`) with a factory, a `days()` has-many on `Listing`, and a `ListingFactory` state that gives a listing specific days. Verify with a feature test that a listing's days load in date order and are deleted with the listing.

## 2. Filtering by specific days

- [x] 2.1 Extend `Listing::scopeRunningBetween` so a listing with days matches only when one of its days falls in the window, comparing with `whereDate`. Verify with tests in `tests/Feature/ListingsPeriodPagesTest.php` covering the spec's scenarios (weekend festival on Today, Wednesday 10 June and Saturday 13 June 2026; pub quiz on This week, Monday 16 and Wednesday 18 November 2026; the 18 to 30 November date range), the first and last day of a window, and a listing without days that is unchanged.
- [x] 2.2 Verify the run-wide pages are unchanged: add a test that the pub quiz shows on the all listings page on 10 November and not on 16 December 2026, and run `tests/Feature/ListingExportTest.php` to confirm the export and count are unaffected for listings without days.

## 3. Rules and saving

- [x] 3.1 Add `App\Services\Listings\ListingDays` with `normalise()` (sort and de-duplicate; no days unchanged; one day becomes a one-day listing; otherwise tighten to the first and last day; every day from first to last means no days). Verify with tests for each case, including the pub quiz (typed 1 Nov to 24 Dec, saved 3 Nov to 15 Dec) and the weekend festival (typed 1 to 20 Jun, saved 6 to 20 Jun).
- [x] 3.2 Add `save()`, which fills and saves the listing and replaces its days in one transaction, and `copy()`, which copies only the days inside the target's run and then normalises the target. Verify with tests that a failed day write leaves the listing unsaved, and that `copy()` with no days inside the run leaves the target's dates and gives it no days.

## 4. Dashboard: validation, saving, duplicating

- [x] 4.1 Add `set_specific_days` and `specific_days` / `specific_days.*` rules to `ListingRequest` as in design.md, with `end_date` required when specific days are set. Verify with tests in `tests/Feature/DashboardListingTest.php` for no day ticked, a day outside the run, and a malformed date, each returning an error on `specific_days`.
- [x] 4.2 Route `store` and `update` in `Dashboard\ListingController` through `ListingDays::save()`, passing no days when `set_specific_days` is off, and give the Edit page a `specificDays` prop of `YYYY-MM-DD` strings. Verify with tests that store and update tighten the run, that unticking clears the days, and that the Edit page carries the days.
- [x] 4.3 Make `duplicate` copy the days through `ListingDays::copy()`. Verify in `tests/Feature/DashboardListingDuplicateTest.php` that a duplicated pub quiz keeps its run and its four days.

## 5. Dashboard: the calendar picker

- [x] 5.1 Make `DateField.vue` emit its `YYYY-MM-DD` value whenever it changes, by typing or the browser picker. Verify that the existing browser test `tests/Browser/DashboardListingDatesTest.php` still passes.
- [x] 5.2 Split the Monday-first month-grid arithmetic out of `buildMonth` in `resources/js/lib/listingCalendar.ts` into an exported helper, with the bookmarks calendar using it. Verify the bookmarks calendar renders as before in its existing browser test.
- [x] 5.3 Build `resources/js/components/dashboard/SpecificDaysPicker.vue` as in design.md and wire it into `ListingFormFields.vue` below Additional date information, with the run fed from the two `DateField`s and the initial days from the Edit prop:
  - the checkbox, shown only when the end is after the start
  - help text about tightening
  - Select all and Clear all
  - weekday-letter toggles
  - disabled days outside the run
  - "N of M days chosen"
  - the dropped-days note
  - hidden `specific_days[]` inputs

  Add `days` to the listing type in `resources/js/types/global.d.ts`. Verify with browser tests in `tests/Browser/DashboardListingFormTest.php`:
  - the checkbox hides for a one-day event
  - Select all then M gives 50 of 58 for 12 Sep to 8 Nov 2026
  - the two S letters tick 6, 7, 13, 14 and 20 June
  - shortening the run drops a day and says so
  - saving the pub quiz stores 3 Nov to 15 Dec with four days
  - the layout at phone width has no horizontal scroll

## 6. Bookmarks calendar

- [x] 6.1 Eager-load `days:listing_id,day` for the Dashboard's bookmarks panel (`Dashboard\HomeController`, where the calendar is drawn), and make `listingsByDay` in `listingCalendar.ts` place a listing on its days from today when it has any. Verify with a feature test that the panel's listings carry their days and that the listings pages do not load them, and with a browser test that a bookmarked listing on two specific days appears on those days only (dated 2099, as the browser's clock cannot be moved).

## 7. Add to calendar

- [x] 7.1 Make `App\Support\ListingCalendar` write one all-day event per specific day, with UID `listing-{id}-{Ymd}@goodonpaper.info`, and keep the single spanning event and its UID for listings without days. Verify in `tests/Feature/ListingCalendarTest.php` that the pub quiz's file holds four events on the right dates with stable UIDs, and that an ordinary run is unchanged.

## 8. The Office

- [x] 8.1 Add a detail-only "Specific days" field to `app/Nova/Listing.php`, shown only when the listing has days, listing them and linking to `route('dashboard.listings.edit', $listing)` as "Edit days in the Dashboard". Verify with a test in `tests/Feature/NovaListingTest.php` that the field and link appear for the pub quiz and not for a listing without days. Check the detail page by eye in Light, Dark and Goblin: no colour outside the theme.
- [x] 8.2 Make the start and end `Date` fields read-only, with help text, while the listing has days. Verify with a test that an Office update posting new dates for the pub quiz leaves its dates and days unchanged, while a listing without days still takes new dates.
- [x] 8.3 Copy days on Office duplication: implement `afterCreate()` on the Nova `Listing` resource to call `ListingDays::copy()` when the request carries `fromResourceId`. Verify with tests that a copy with unchanged dates keeps the pub quiz's four days, and that a copy moved to 5 to 26 January 2027 has none and keeps its dates.

## 9. Checks before handing over

- [x] 9.1 Run `vendor/bin/pint --dirty`, then `composer test:lint`, `composer analyse`, `vendor/bin/sloppy diff main --fail-on=high`, `php artisan test tests/Feature tests/Unit --compact`, and (after `npm run build`, which rewrites `public/build`) `php artisan test tests/Browser --compact`. Verify that every one passes, and leave the work uncommitted with a list of the files ready to commit.

## Workflow follow-up

- The maintainer runs the `listing_days` migration by hand on staging and on live before pushing.
- The maintainer commits and pushes; the push to `main` deploys to staging and then live.
- Archive the change with `/opsx:archive` once it has shipped.
