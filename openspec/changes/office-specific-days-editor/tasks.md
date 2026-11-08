# Tasks

## 1. Shared behaviour for both calendars

- [ ] 1.1 Move the picker's state and actions out of `resources/js/components/dashboard/SpecificDaysPicker.vue` into `resources/js/composables/useSpecificDays.ts`, as in design.md, and rebuild the Dashboard picker on it with no change to its markup or look. Verify that every test in `tests/Browser/DashboardListingFormTest.php` still passes after `npm run build`, and that `npx vue-tsc --noEmit` and ESLint are clean.

## 2. Saving rules the Office can call

- [ ] 2.1 Split `ListingDays::writeDays(Listing, list<string>)` out of `save()` (delete the listing's days, then one insert) and have `save()` call it. Verify with a test in `tests/Feature/ListingDaysTest.php` that `writeDays` replaces a listing's days, and that the existing `ListingDaysTest` and `DashboardListingTest` still pass.
- [ ] 2.2 Add `App\Rules\SpecificDaysWithinRun` for the Office's JSON value, with its messages as public constants, and point `ListingRequest::messages()` at the same constants. Verify with tests for each failure: no day ticked, a day outside the run, a malformed day, no end date. Check the messages match the ones the Dashboard tests already assert, and that `DashboardListingTest` still passes unchanged.

## 3. The SpecificDays field package

- [ ] 3.1 Scaffold `nova-components/SpecificDays` (`ultrasimplified/specific-days`) on VideoEmbed's pattern:
  - `composer.json`, service provider and field class (following `start_date`/`end_date` by default)
  - `vite.config.js` with the `@` alias to `resources/js`, and an npm script `build:nova-specific-days`
  - a path repository and `require` in the app's `composer.json`, then `composer update ultrasimplified/specific-days`

  Verify that `composer show ultrasimplified/specific-days` lists it, the service provider is discovered, and `npm run build:nova-specific-days` writes `dist/js/field.js` and `dist/css/field.css`.
- [ ] 3.2 Build the form component on `useSpecificDays` with Nova's `FormField` and `HandlesValidationErrors` mixins:
  - `fill(formData)` through `fillIfVisible`, sending `{enabled, days}` as JSON
  - dates followed through `getFieldAttributeChangeEventName` listeners, removed on unmount
  - the checkbox, chips, calendar, count and left-out note as in the Dashboard
  - `field.css` drawn from Nova's colour variables, as in design.md

  Also build the detail component, which shows the server's text. Verify the build is clean and commit-ready in `dist`. Behaviour is verified in group 4.

## 4. Wiring the field into the Office

- [ ] 4.1 In `ListingDateFields`:
  - add the field after the dates, with `resolveUsing`, `displayUsing` (the existing day-list text, without the link), `showOnDetail` and `fillUsing` (normalise, set the tightened dates, return a closure calling `writeDays`)
  - give it the `SpecificDaysWithinRun` rule
  - remove the read-only dates, their help text, `datesFollowSpecificDays()`, the computed link field and its HTML

  Update `tests/Feature/NovaListingTest.php` to the new spec, and say in the test file's comments why the read-only tests changed (the Office now edits days). The tests should cover:
  - the detail page lists the days with no link
  - an Office update with days tightens the run and writes them
  - unticking clears them
  - updating dates on a listing with days now takes effect
  - each validation failure returns the shared message on the field
- [ ] 4.2 Remove `Listing::afterCreate()` and its copying, and rewrite the two Office replicate tests:
  - asking for the replicate form fields (`creation-fields?fromResourceId=`) for the pub quiz gives the field its four days. Use a listing with an empty gallery and line-up, as design.md notes.
  - saving the copy with those days gives it the same days and run
  - saving it moved to 5–26 January 2027 with the box still ticked is refused with the "choose at least one day" message
  - saving it moved and unticked gives a copy with no days

  Verify that `NovaListingTest` passes.
- [ ] 4.3 Extend `tests/Browser/OfficeListingSaveTest.php`:
  - edit a two-week listing in the Office, tick "Set specific days", press Sat and Sun, save, and check the stored days and tightened run
  - change the end date in the Office's own date field and check the calendar redraws and leaves out the days the run no longer reaches
  - open the pub quiz's edit form and check its four days are ticked with the dates editable
  - `assertNoJavaScriptErrors()` throughout

  Verify they pass after `npm run build:nova-specific-days`. Check the edit form by eye in Light, Dark and Goblin: ticked days and chips that are on use the Office's primary colour, which turns green in Goblin, with no other hue.

## 5. Checks before handing over

- [ ] 5.1 Run `vendor/bin/pint --dirty`, `composer test:lint`, `composer analyse`, `vendor/bin/sloppy diff main --fail-on=high`, and `php artisan test tests/Feature tests/Unit --compact`. Then, after `npm run build` (which rewrites `public/build`) and `npm run build:nova-specific-days`, run `php artisan test tests/Browser --compact`. Verify every one passes. Leave the work uncommitted and list the files ready to commit, including `composer.json`, `composer.lock` and the package's `dist`.

## Workflow follow-up

- No migration is needed.
- The maintainer commits and pushes. The push to `main` deploys to staging and then live, installing the new package.
- Archive the change with `/opsx:archive` once it has shipped.
