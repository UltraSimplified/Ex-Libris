# Proposal

## Why

Every listings page sorts by each listing's last day. That suits events already under way: what closes soonest comes first. But a multi-day event that has not started yet is filed under its last day too, so it turns up weeks below the day it opens. A show opening in two days sits among events at the end of the month. On the All page this reads as chaotic, and nothing on the page explains why an event is where it is.

## What Changes

- Listings are ordered by **the next day each one is on**, counted from the page's first day: today on the run-wide pages, or the first day of a chosen date range.
  - A run that has not started is filed under its start date.
  - A run already under way is filed under that first day.
  - A listing with specific days is filed under its next chosen day.
- Listings filed under the same day are ordered by **days left to the end of the run**, then by title. Specific days do not count here: the pub quiz's last Tuesday is the end of its run.
- **Day headings** divide the list: TODAY, TOMORROW, then the weekday in full with the day and month (SATURDAY 14 NOV). Past the end of the month after the page's first day, there is **one heading per month** (JANUARY 2027), because further out almost every listing would otherwise get a heading of its own.
- Headings appear on every listings page except those that cover a single day: Today, Tomorrow, or a chosen range of one day.
- Headings follow what is visible. A search, a genre filter or an emptied bookmark that leaves a day with nothing to show also removes that day's heading. Adverts still count listings only, and never come between a heading and its first listing.
- The Dashboard's bookmarks panel takes the new order but no headings. Its calendar view already shows days.
- **BREAKING (public order):** every listings page reorders. The Today page keeps its order exactly. Elsewhere, multi-day events that have not started move up to their opening day, and runs already under way move into today's group.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `listing-dates`:
  - New requirements for the order of the listings pages and the bookmarks panel.
  - New requirements for the day and month headings on the listings pages.
  - The capability already owns when a listing is on and how the listings pages honour it; the order and the headings are built from the same days.

## Impact

- **Public listings pages and the Dashboard.** The public pages change order and gain headings. The Dashboard's bookmarks panel changes order only. **The Office is not touched.** Its own filters and sorting stay as they are.
- **No migration and no live data changes.** The day is worked out in the query from `start_date`, `end_date` and `listing_days`.
- **Not data, but worth knowing:** a weekly class entered as one long run with no specific days is taken at its word. It sits in today's group every day until it is given specific days. Several on live already have them; a few do not yet. Tidying those is a job for the Dashboard, outside this change.
- **Code:**
  - `app/Models/Listing.php`: `scopeFinishingSoonest` is replaced by a scope that orders by the next day and returns that day with each listing.
  - `app/Http/Controllers/ListingController.php`: every action switches to the new scope, passing its first day, and says whether to show headings.
  - `app/Http/Controllers/Dashboard/HomeController.php`: the bookmarks panel switches to the new scope.
  - `resources/js/components/GOP/ListingsIndex.vue`: lays out heading rows among the listing and advert rows.
  - `resources/js/lib/listingDates.ts`: labels the headings.
  - `resources/js/types/global.d.ts`: the listing type gains its day.
- **Tests:**
  - New feature tests for the order.
  - A browser test for the headings.
  - `tests/Browser/ListingsAdvertSlotsTest.php` learns the new kind of row.
  - The two "soonest finishing first" tests in `tests/Feature/DashboardBookmarksPanelTest.php` and `tests/Feature/GuestBookmarkTest.php` are renamed to describe the new order. They keep their assertions, which still hold.
- **Unchanged:** the gig widget, which already sorts by start date, and the Office's listing filters.
