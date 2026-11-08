# Proposal

## Why

A multi-day listing is treated as on every day from its start to its end. Plenty of runs are not like that: a pub quiz every other Tuesday, a festival on Saturdays and Sundays, a gallery closed on Mondays, a swap shop on three unrelated dates. Today the only way to say so is the free-text "Additional date information", so the Today, Tomorrow, This week and Next week pages show these events on days nothing is happening. About one multi-day listing in twenty already carries such a note, and every one of them shows up on the wrong days.

## What Changes

- The Dashboard listing form gains a "Set specific days" checkbox below Additional date information. Ticking it shows a month-by-month calendar of every day between the start and end dates, where the person picks the days the event is on.
- The calendar has Select all and Clear all buttons. A row of outlined Mon to Sun buttons above the calendar ticks every one of that weekday in the run, or clears them when they are all already ticked. A gallery closed on Mondays is Select all, then Mon.
- Saving with specific days moves the start and end dates in to the first and last chosen day. One chosen day saves as an ordinary one-day listing; every day chosen saves as an ordinary run with no specific days.
- A listing with specific days appears on a day-based listings page (Today, Tomorrow, This week, Next week, a chosen date range) only when one of its days falls in that window. Listings without specific days behave exactly as now.
- The bookmarks calendar places such a listing only on its chosen days, and "Add to calendar" writes one all-day event per chosen day rather than one event spanning the run.
- Duplicating a listing, in the Dashboard or the Office, copies its specific days.
- The Office shows a listing's specific days read-only, with a link to edit them in the Dashboard. While a listing has specific days, its start and end dates are read-only in the Office too.
- Additional date information stays as it is: free text shown in brackets after the dates, for what the calendar cannot say ("Thursday 7pm, Sunday 2pm").

Out of scope for this change: showing the matching day on a day-based listings row, a calendar picker inside the Office, one JSON-LD event per day, and a specific-days column in the CSV export.

## Capabilities

### New Capabilities

- `listing-dates`: when a listing is on - its run from start to end, the optional set of specific days within it, how those are entered and kept consistent, and how the listings pages, the bookmarks calendar, calendar export and duplication honour them.

### Modified Capabilities

None. There are no existing specs.

## Impact

- **Both sides.** The Dashboard gets the picker and does the saving; the Office gets read-only display, read-only dates for such listings, and day-copying on duplicate.
- **Migration.** Adds a `listing_days` table (listing, day). Existing listings need no backfill: a listing with no rows behaves as it does now. The migration must be run on staging and live before the deploy.
- **Live data.** None changes. The 15 listings that describe their days in free text keep showing on every day until somebody ticks their days.
- **Code.**
  - `app/Models/Listing.php`: the `runningBetween` scope and a `days` relation.
  - A new `ListingDay` model.
  - Saving: a small class used by the Dashboard's `store`, `update` and `duplicate`.
  - `app/Http/Requests/Dashboard/ListingRequest.php`: validation for the days.
  - Calendar export: `app/Support/ListingCalendar.php`.
  - The Office: `app/Nova/Listing.php`.
  - Dashboard form: `resources/js/components/dashboard/ListingFormFields.vue`, `DateField.vue` and a new picker component.
  - Bookmarks calendar: `resources/js/lib/listingCalendar.ts`, and the bookmarks panel's query in `app/Http/Controllers/Dashboard/HomeController.php`.
- **Unchanged.** Everything that only reads the start and end dates stays correct once the run is tightened:
  - the `current()` and `finishingSoonest()` scopes
  - the dates line
  - JSON-LD
  - widgets
  - the sitemap
  - bookmark pruning
  - the Office's period filter
