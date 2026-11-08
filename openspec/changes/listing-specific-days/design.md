# Design

## Context

See proposal.md for why, and specs/listing-dates/spec.md for the behaviour.

- **Where the date filtering lives.** All of it goes through scopes on `App\Models\Listing`:
  - `runningBetween($from, $to)` is an overlap test on the run. `runningOn` wraps it, and so do the Today, Tomorrow, This week, Next week and date-range pages, the CSV export and the Office's listings-export card.
  - `current()` keeps a listing until its last day, `DATE(COALESCE(end_date, start_date))`. All the run-wide pages and bookmark pruning use it.
  - `finishingSoonest()` orders by that same last day.
- **Column types.** `listings.start_date` and `end_date` are `dateTime` columns holding midnight, so every comparison wraps them in `DATE()` / `whereDate`. The tests run on SQLite, which stores dates as text, so a bare string comparison against a column holding `2026-11-03 00:00:00` silently fails there.
- **The Dashboard form.** `ListingFormFields.vue` is shared by Create and Edit. It is an uncontrolled form: an Inertia `<Form>` submits named inputs. `DateField.vue` holds each date's `YYYY-MM-DD` in a hidden input and does not report changes, so nothing else on the form can react to the start or end date today.
- **Dashboard saving.** `Dashboard\ListingController` saves in `store`, `update` and `duplicate`. Those actions already carry thumbnail and gallery handling, so new logic should go in a class of its own rather than into the controller.
- **The bookmarks calendar.** `resources/js/lib/listingCalendar.ts` builds Monday-first month grids from each listing's start and end. Only `BookmarkedListingsCalendar.vue` uses it.
- **Add to calendar.** `App\Support\ListingCalendar` writes one all-day `VEVENT` per listing, with UID `listing-{id}@goodonpaper.info`.
- **Office duplication.** Nova 5 replicates by opening a prefilled create form. Its `CreateForm.vue` posts `fromResourceId` with the store request, so `Listing::afterCreate()` can tell a copy from a new listing and find the original.
- **Office access to the Dashboard.** Staff can already open any listing's Dashboard edit form, because `ListingPolicy::before()` allows everything for accounts that can see Nova.

## Goals / Non-Goals

**Goals:**
- Keep one rule for "is this listing on in this window", in `runningBetween`, so every day-based page and export honours specific days without changes of their own.
- Keep the stored run equal to the first and last specific day, so everything that reads only the start and end stays correct unchanged.
- Leave listings without specific days on exactly the queries they run today.

**Non-Goals:**
- Recurrence rules ("every other Tuesday") stored as rules. The calendar's shortcuts produce plain days and only the days are stored.
- Times per day. Times stay in the time description and additional date information.
- Loading specific days on pages that do not draw them.

## Decisions

### Store each specific day as a row in a `listing_days` table

The table holds `id`, `listing_id` (constrained, cascade on delete) and `day` (`date`). It has a unique index on (`listing_id`, `day`) and an index on `day`. A `ListingDay` model has no timestamps and casts `day` as `date:Y-m-d`, and `Listing` gets a `days()` has-many relation.

*Alternatives considered:*
- **A JSON column of days on `listings`.** Filtering would need JSON functions, which differ between MySQL and the SQLite tests.
- **A weekday bitmask.** It cannot express "every other Tuesday", "2nd and 4th Tuesday" or unrelated dates, all of which appear in live data.

A listing with no rows means "every day of the run", so nothing needs backfilling.

### `runningBetween` adds one condition; the other scopes are untouched

`runningBetween` keeps its overlap test and adds a condition: either the listing has no days, or it has a day between `from` and `to`. Day comparisons use `whereDate`, as the run comparisons already do, so they behave the same whether SQLite stores `2026-11-03` or `2026-11-03 00:00:00`.

`current()` and `finishingSoonest()` stay as they are. Because the run is tightened on save, "last day" is already the last specific day.

*Alternative considered:* also expressing `current()` in terms of days. Tightening makes this unnecessary, and it would add a subquery to every run-wide page for no difference in results.

### A `ListingDays` service owns the rules and the writes

`App\Services\Listings\ListingDays` has three jobs:

- **`normalise(attributes, days)`.** A pure function that returns the attributes to save and the days to store:
  - Sort and de-duplicate the days.
  - No days: attributes unchanged, no days stored.
  - One day: start becomes that day, end becomes null, no days stored.
  - Otherwise: start and end become the first and last day. If every day from first to last is chosen, store no days; this covers Select all, and also a contiguous block picked from the middle, which is simply a shorter ordinary run.
- **`save(listing, attributes, days)`.** Fills and saves the listing and replaces its days in one transaction.
- **`copy(from, to)`.** Copies the days of `from` that fall inside the run of `to`, then normalises and saves `to`.

The Dashboard's `store` and `update` call `save`. The Dashboard's `duplicate` and the Office's `afterCreate` (when `fromResourceId` is present) call `copy`. A Dashboard duplicate keeps the original's dates, so every day is copied. In the Office, the rule in the spec (only the days inside the copy's run) comes from the same method.

*Alternative considered:* a model `saving` hook. That would run on every save, including Nova's ordinary edits and factories, and the days arrive from outside the model's attributes.

### Validation in `ListingRequest`

- `set_specific_days`: boolean.
- `specific_days`: an array, required with at least one entry when `set_specific_days` is true.
- `specific_days.*`: `date_format:Y-m-d`, `distinct`, `after_or_equal:start_date` and `before_or_equal:end_date`.
- `end_date`: required when `set_specific_days` is true.

When `set_specific_days` is false, the controller passes no days, whatever else was posted. The form shows one error under the calendar: the first of `specific_days` or `specific_days.*`.

### Form: a `SpecificDaysPicker` component, fed by `DateField`

`DateField` gains an emitted event carrying its `YYYY-MM-DD` whenever it changes, by typing or by the browser's picker. `ListingFormFields` keeps the current start and end in refs from those events (seeded from the listing) and passes them to a new `resources/js/components/dashboard/SpecificDaysPicker.vue`. The picker takes the run, the initial days (an Edit-page prop of `YYYY-MM-DD` strings) and the field names, and renders:

- **The checkbox.** "Set specific days", named `set_specific_days`, shown only when the end date is after the start date.
- **Help text.** Says the start and end will move to the first and last chosen day.
- **Controls.** Select all, Clear all, and a seven-column Monday-first grid per month. Above the months, a row of outlined Mon to Sun buttons each toggle that weekday across the run.
- **Day buttons.** Each is a toggle with `aria-pressed`, labelled with the full date. Days outside the run are drawn but disabled.
- **Status line.** "N of M days chosen", plus a note while ticked days fall outside the run. Those days are held back rather than discarded: they are not drawn or sent, and come back if the run is widened again, so a date typed a character at a time cannot throw ticks away.
- **What is submitted.** One hidden `specific_days[]` input per ticked day, only while the checkbox is ticked.

The month-grid arithmetic in `listingCalendar.ts` (`buildMonth`) is split into an exported helper that returns the Monday-first weeks for a month. The bookmarks calendar and the picker then draw identical grids.

The layout is the same at every width. Seven columns of about 40px tap targets fit a 343px phone column, and wider screens only get more room.

*Alternative considered:* a third-party date-range or multi-date picker. It would add a dependency, and none we know of has the weekday toggle or matches the existing calendar's look.

### Bookmarks calendar reads days when present

The calendar is drawn in the Dashboard's bookmarks panel, so `Dashboard\HomeController` eager-loads `days:listing_id,day` with the bookmarked listings, and each listing carries `days: [{ day: 'YYYY-MM-DD' }]`. `listingsByDay` places a listing on its days from today when it has any, and on its run as now when it has none. No public listings page loads days.

### Calendar export: one `VEVENT` per specific day

`ListingCalendar::event()` returns a list of events. A listing with days gets one all-day event per day, with UID `listing-{id}-{Ymd}@goodonpaper.info`; the summary, location, description and URL are the same on each. A listing without days keeps its single event and its existing UID.

*Alternative considered:* one event with `RDATE`s. Support for importing it is patchy across calendar apps, and a missed `RDATE` would show the event on its first day only.

### Office: read-only days, read-only dates, copy on duplicate

- **Specific days field.** A detail-only Text field, shown only when the listing has days. It lists them as `j M` (with the year when the run crosses one) and gives a plain link to `route('dashboard.listings.edit', $listing)`, "Edit days in the Dashboard". It has no colour, so it reads correctly in the Goblin theme.
- **Dates.** The start and end `Date` fields become `readonly()` when the listing exists and has days, with help text pointing to the Dashboard. Nova does not fill read-only fields on update, so the dates cannot drift from the days.
- **Duplicate.** `afterCreate(NovaRequest $request, Model $model)` calls `ListingDays::copy()` when the request carries `fromResourceId`.

## Risks / Trade-offs

- **[A browser calendar that imported the old single event keeps it]** If a listing gains specific days after someone imported its calendar file, their app holds the old spanning event (UID `listing-{id}`) next to the new per-day ones. → Rare, and only for people who imported before the change. Accepted.
- **[SQLite and MySQL store `day` differently]** Through the `date` cast, SQLite may hold `2026-11-03 00:00:00` where MySQL holds `2026-11-03`. → Compare with `whereDate`, and have the feature tests (on SQLite) cover the boundaries: the first and last day of a window, and a day just outside it.
- **[Extra condition on every day-based query]** `runningBetween` gains an `EXISTS` / `NOT EXISTS` pair. → The tables are small (about 1,100 listings) and the unique index on (`listing_id`, `day`) serves both subqueries.
- **[Editors expect the dates they typed]** Tightening changes the dates after save. → The picker's help text says so before saving, and the dates show the tightened values when the form reopens.
- **[Office replicate form shows editable dates]** The new record has no days yet, so its dates can be edited. → `copy()` keeps only the days inside the saved run and tightens to them. With none inside, the copy keeps its dates and has no days, as the spec says.
- **[The retired monthly import calls `runningBetween`]** `listings:import` uses it to find hand-added twins. → The import is retired. If it were run again, a listing with specific days would only count as a twin on those days, which is the right answer anyway.

## Migration Plan

1. One migration creates `listing_days`; its `down()` drops the table. No data changes, no backfill.
2. The migration is run by hand on staging and live before the code is pushed, so the deploy finds nothing waiting.
3. Rollback: revert the code, then roll back the migration. Days entered in the meantime are lost, and those listings go back to showing on every day of their tightened run.
