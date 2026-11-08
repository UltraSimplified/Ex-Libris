# Design

## Context

See proposal.md for why, and specs/listing-dates/spec.md for the behaviour.

- **The Office side today.**
  - `app/Nova/Fields/ListingDateFields.php` builds the Office's date fields. It makes the start and end read-only while a listing has days (Nova leaves read-only fields out of validation and filling), and shows the days as a computed HTML field with a Dashboard link.
  - `app/Nova/Listing.php` copies days in `afterCreate()` when the request carries `fromResourceId`.
- **How custom Office fields are built.** `nova-components/VideoEmbed` and `nova-components/Gallery` are local Composer packages (path repositories, `@dev`). Each:
  - has a service provider calling `Nova::script()` / `Nova::style()`
  - is built by the app's own Vite through a config in the package (`npm run build:nova-gallery`), with `vue` and `laravel-nova` left external as `window.Vue` and `window.LaravelNova`
  - commits its `dist`
  - keeps its own plain CSS, because Nova's stylesheet only holds the utilities Nova's own templates use. Colours come from Nova's CSS variables (`--colors-primary-*`, `--colors-gray-*`), which `public/css/nova-overrides.css` redefines in Goblin mode.

  CI installs these packages with `COMPOSER_MIRROR_PATH_REPOS=1` and copies `vendor` and `nova-components` to the servers.
- **Nova's hooks.**
  - A field's `fill` may return a closure. Nova runs it after the model is saved, inside the same transaction (`ResourceStoreController`, `ResourceUpdateController`).
  - Every form field announces changes on Nova's event bus, as `{formUniqueId}-{attribute}-change` (`FormEvents::emitFieldValueChange`). `getFieldAttributeChangeEventName()` gives the name to listen for.
  - Nova's `dependsOn` listens on the same bus, but its re-sync replaces the field's value with the server's copy (`DependentFormField::setInitialValue`), which would throw away ticks made in the browser.
- **Replicate.** Nova builds the copy's form from the original resource (`ReplicateViewResource`), so a field resolved from the listing arrives with the original's values.
- **The Dashboard picker.** `resources/js/components/dashboard/SpecificDaysPicker.vue` holds both the behaviour and the Dashboard's look:
  - **behaviour:** run days, held-back days, Select all and Clear all, weekday toggles, the count
  - **look:** hi-vis circles, DIN chips
- **The rules.** `App\Services\Listings\ListingDays` holds them (`normalise`, `save`, `copy`). `save` fills the listing and writes its days in one transaction, which suits the Dashboard controller but not Nova, where Nova does the saving.
- **A test hazard.** Factory listings have no gallery or line-up. Nova's update-fields endpoint failed on such a listing with "Unable to parse incoming Flexible content" when probed during the last change, so any feature test that asks Nova for a listing's form fields has to give it an empty gallery and line-up.

## Goals / Non-Goals

**Goals:**
- One implementation of the calendar's behaviour, shared by the Dashboard and the Office.
- Office saving goes through the same `ListingDays` rules as the Dashboard.
- The custom field package stays generic (a day picker for a run), with everything about listings kept in the app, as with Gallery.

**Non-Goals:**
- Changing anything a Dashboard user sees.
- Showing specific days on the Office index.
- Editing days from Nova's inline or relation-create modals.

## Decisions

### Share the behaviour as a composable; keep two looks

`resources/js/composables/useSpecificDays.ts` takes refs for the run's start and end and the initial days, and returns:
- **state:** `enabled`, `chosen`, `runDays`, `chosenInRun`, `leftOut`, `months`
- **actions:** `toggleDay`, `toggleWeekday`, `selectAll`, `clearAll`
- **helpers:** `runDaysOn`, `weekdayTicked`

`SpecificDaysPicker.vue` becomes the Dashboard's template over it, and the Office field's form component is the Office's template over it.

*Alternatives considered:*
- **One shared component.** Its classes are the Dashboard's Tailwind theme (`bg-hivis`, `font-din`), which the Office does not load, and hi-vis would break Goblin.
- **Copying the logic.** The two would drift, which is what this change sets out to avoid.

### A generic `SpecificDays` Nova field package

`nova-components/SpecificDays` (`ultrasimplified/specific-days`) follows VideoEmbed:
- **Packaging:** a service provider, `dist/js/field.js` and `dist/css/field.css`, and `npm run build:nova-specific-days`.
- **Build:** its Vite config aliases `@` to `resources/js`, so it imports the composable and `listingCalendar.ts` / `listingDates.ts` as the Dashboard does. `vue` stays external, so the composable runs on Nova's Vue.
- **PHP:** the field class only knows the names of the two date attributes it follows (default `start_date` and `end_date`). It sends their current values and the days with the field.
- **Components:**
  - **FormField:** uses Nova's `FormField` and `HandlesValidationErrors` mixins and implements `fill(formData)` with `fillIfVisible`. A custom field without the mixin's `fill` is what once made Office saves fail with "t.fill is not a function".
  - **DetailField:** shows the text the server formats.

*Alternative considered:* writing the field class in `app/Nova/Fields`, registering its script from `NovaServiceProvider` and building the JS into `public/`. That breaks from how the other three custom fields are packaged, built and deployed.

### Follow the dates through Nova's event bus, not `dependsOn`

The form component seeds its start and end from the field's meta. It then listens for `getFieldAttributeChangeEventName('start_date')` and `('end_date')`, and stops listening on unmount. So the calendar follows the dates as they are typed or picked, with no request to the server, and the ticks live only in the component.

*Alternative considered:* `dependsOn`. Every date change would cost a request, and the re-sync's `setInitialValue` would overwrite ticks with the stored days.

### What the field sends, and how Nova saves it

`fill` sends JSON: `{"enabled": bool, "days": ["2026-11-03", …]}`, where `days` holds only the ticked days inside the current run. In the app, `ListingDateFields` adds the field after the two dates, with:
- **`resolveUsing`:** the listing's days as `YYYY-MM-DD`, with `enabled` set when there are any.
- **`displayUsing`:** the existing "3 Nov, 17 Nov, 1 Dec, 15 Dec 2026" text, moved from `specificDaysHtml()` and now without the link. `showOnDetail` shows it only when the listing has days.
- **`rules`:** a new `App\Rules\SpecificDaysWithinRun` (see below).
- **`fillUsing`:**
  1. Decode the JSON.
  2. Pass the dates Nova has already filled, and the days, through `ListingDays::normalise()`.
  3. Set the tightened start and end on the model.
  4. Return a closure that writes the days once Nova has saved the listing.

`ListingDays` gains a public `writeDays(Listing, list<string>)` (delete, then one insert), which `save()` now calls too. So the Dashboard and the Office write days the same way.

The field sits after the dates in the field list, so its `fillUsing` runs after Nova has filled them and can overwrite them with the tightened run.

### One set of validation messages

`App\Rules\SpecificDaysWithinRun` validates the Office's JSON. When `enabled` is set:
- there is at least one day
- every day is `Y-m-d`
- the request has an end date
- every day lies between the request's start and end dates

Its messages are public constants. `ListingRequest::messages()` refers to the same constants, so the Dashboard and the Office cannot word an error differently. The Dashboard keeps its declarative array rules, which already work and are tested.

### Removing the read-only Office behaviour and `afterCreate`

`ListingDateFields` loses the `readonly()` callbacks, the update-only help text, `datesFollowSpecificDays()` and the link. `Listing::afterCreate()` goes, because Replicate now carries the days through the form. `ListingDays::copy()` stays for the Dashboard's Duplicate.

### The Office's look

`field.css` draws the calendar in the Office's palette:
- **Weekday chips:** square-cornered, filled with `--colors-gray-800` and white text when off, `--colors-primary-500` when on. Select all and Clear all are chips that are always off.
- **Days:** ticked days are `--colors-primary-500` circles with bold white numbers. Days outside the run use `--colors-gray-400`. Today is underlined.
- **Dark mode:** `.dark` variants keep the contrast, using Nova's own dark class.

Goblin mode redefines these variables in green, so nothing more is needed there.

## Risks / Trade-offs

- **[Nova's change-event names are a mixin helper, not documented API]** A Nova upgrade could rename them. → The browser test drives the real Office form, typing a date and checking the calendar redraws, so a rename fails CI rather than going unnoticed.
- **[Nova's Date field may announce its value in another shape]** The composable expects `YYYY-MM-DD`. → The form component normalises what it receives with the existing `parseDay`/`dayKey` helpers, and the browser test types dates in the Office's own date field.
- **[The Flexible content error in feature tests]** → Tests that request form fields create listings with an empty gallery and line-up. Saving is tested through the store and update endpoints, which already work in `NovaListingTest`.
- **[Office behaviour on Replicate changes]** Moving a copy's dates past every day now asks for days or an untick, where before it quietly saved no days. → This is deliberate, matches the Dashboard, and is written into the spec.
- **[Two templates over one composable can still differ in markup]** → Both have browser tests asserting the same behaviour, written as separate tests against the same scenarios.
- **[A new dependency]** `composer.json` gains a path repository and `ultrasimplified/specific-days: @dev`, and `composer.lock` changes. → Same shape as the three existing custom fields. CI's install and copy steps already handle it.

## Migration Plan

1. No database migration. The `listing_days` table already exists on staging and live.
2. Build the field with `npm run build:nova-specific-days` and commit its `dist`, as with the other fields. Run `composer update ultrasimplified/specific-days` so `composer.lock` records it.
3. Deploy as usual. CI installs the package and copies it to the servers.
4. Rollback: revert the commit. The Office goes back to read-only days with the Dashboard link, and no data is affected.
