# Design

## Context

See proposal.md for why, and specs/listing-dates/spec.md for the behaviour.

- **One scope orders everything.** `Listing::scopeFinishingSoonest()` sorts by `DATE(COALESCE(end_date, start_date))`, then `start_date`, then `title`. It is used by:
  - every action in `app/Http/Controllers/ListingController.php`: all, bookmarked, dates, venue, promoter, by_category, today, tomorrow, thisweek and nextweek
  - the bookmarks panel in `app/Http/Controllers/Dashboard/HomeController.php`

  The gig widget (`WidgetFeed`) sorts by start date on its own and is not part of this change.
- **What the browser already does with the list.** `resources/js/components/GOP/ListingsIndex.vue` filters the server's list three ways before drawing it: My Bookmarks drops rows whose heart has been emptied, the genre pills narrow it, and search narrows it further. It then builds `rows`, adding an advert after every `advertEvery` listings, never as the last row. The rows are direct children of one grid, because `useScrollReveal` observes `:scope > div` and a wrapper would hide them. They sit in a `TransitionGroup`, so rows fade in and out as filters change.
- **Specific days are not sent to the listings pages.** Only the Dashboard panel eager-loads `days:listing_id,day`. The browser cannot work out a listing's next chosen day for itself.
- **The two databases.** The site runs on MariaDB and the tests on in-memory SQLite. Raw SQL has to mean the same on both. `GREATEST()` is MariaDB only, and SQLite's two-argument `MAX()` is not portable the other way.
- **`listing_days`** has a unique index on `(listing_id, day)`, which serves a per-listing lookup of the next day.

## Goals / Non-Goals

**Goals:**
- The order and the headings come from one value, worked out once, so they cannot disagree.
- The same scope serves every list, with only its first day varying.
- Headings respond to search, genres and bookmarks without asking the server again.

**Non-Goals:**
- Changing which listings each page shows. Only their order and the headings change.
- Headings in the Dashboard bookmarks panel, or sticky headings.
- Fixing weekly classes entered as long runs. That is data, done in the Dashboard.
- Tidying the heading levels inside listing rows, whose titles are `h1`.

## Decisions

### The next day is worked out in SQL and sent with each listing

`scopeFinishingSoonest` is replaced by `scopeByNextDay(Builder $query, mixed $from = null)`. `$from` is the page's first day and defaults to today. One expression gives the day each listing is filed under:

```sql
COALESCE(
  (SELECT MIN(DATE(listing_days.day)) FROM listing_days
    WHERE listing_days.listing_id = listings.id AND DATE(listing_days.day) >= :from),
  CASE WHEN DATE(listings.start_date) > :from THEN DATE(listings.start_date) ELSE :from END
)
```

- A listing with specific days gets its first chosen day on or after `:from`.
- Any other listing gets its start date if that is later, and otherwise `:from`.
- `:from` is bound as a `Y-m-d` string. Each function used (`DATE`, `MIN`, `COALESCE`, `CASE`) behaves the same on MariaDB and SQLite, and `Y-m-d` text sorts in date order on both.

The scope orders by this expression, then by the existing `DATE(COALESCE(end_date, start_date))`, then by `title`. With `:from` fixed, the last day ascending is the same as days left ascending. The scope also selects the expression as `next_day`, so each listing arrives in the browser carrying the day it was filed under.

The fallback in `COALESCE` matters for one case only: a listing with specific days, none on or after `:from`. The pages' own filters mean such a listing is never shown, but the fallback keeps it ordered sensibly rather than dropping it to NULL.

*Alternatives considered:*
- **Sorting in PHP after `get()`.** This needs every listing's days loaded, sorts the whole result in memory, and Sloppy flags it (SL205).
- **Working the day out in the browser.** This needs specific days sent with every listing on every page, plus a second implementation of the rule in TypeScript.

### The scope keeps the columns a query would otherwise have

Adding a selected expression stops Eloquent from selecting `*` by default. When the query has no columns yet, the scope selects `listings.*` first, then adds `next_day`.

This matters for the Dashboard panel, which goes through `$user->bookmarks()`. Once a `BelongsToMany` query has columns, it adds only the pivot columns, not `listings.*`. With the scope supplying `listings.*`, the panel still gets every listing column, plus `pivot.attending`, which its test already checks.

### Each action passes its first day and whether to head the list

| Action | First day | Headings |
|---|---|---|
| all, bookmarked, venue, promoter, by_category | today | yes |
| thisweek | today | yes |
| nextweek | Monday of next week | yes |
| dates | the range's start | yes, unless start and end are the same day |
| today | today | no |
| tomorrow | tomorrow | no |

The first day is the start of a chosen range even when that start is in the past. This is "the page's first day", as the spec puts it, so a past range reads in date order from its start.

The `Listings` page gets three new props, passed on to `ListingsIndex`:
- `currentDay`: the server's date. The page already has a `today` prop, the flag for the Today tab
- `firstDay`: the page's first day
- `dayHeadings`: whether to head the list

*Alternative considered:* inferring "single day" in the browser from the `today` and `tomorrow` flags. That misses a one-day chosen range, and the controller already knows.

### Headings are built in the browser, after filtering

Which headings to show depends on what is visible, and search, genres and bookmarks are all applied in the browser. `rows` therefore gains a third kind, `{ kind: 'heading', key, label }`, worked out from `visibleListings`:

- **Choosing the heading.** A listing's `next_day` on or before the last day of the month after `firstDay`'s month takes a day heading, keyed by that date. A later one takes a month heading, keyed `YYYY-MM`.
- **Placing it.** A heading goes in before a listing whose key differs from the listing before it.
- **Adverts** are still counted over listings only, and still go in *after* a listing. So an advert can come before a heading, but never between a heading and its first listing.
- **Keys** are `heading-<key>`, so a heading fades out with the last of its listings and back in with the first, as rows already do.

Labels come from `resources/js/lib/listingDates.ts`, so they match the rows:
- `TODAY` and `TOMORROW`, worked out against the server's `currentDay` and not the reader's clock, so the label agrees with what the server counted as today
- otherwise the weekday in full (`SATURDAY 14 NOV`), as a heading has the room for it, with the month and the short year, when it is not this year, as rows already write them
- month headings name the month in full (`JANUARY`), with the year when it is not this year (`JANUARY 2027`)

The heading is a `div` row holding an `h2`, so the reveal observer and the dividers treat it like any other row. Its look stays within the page's existing strips (DIN, uppercase, `border-silver`) and is the same on phone and desktop.

*Alternative considered:* the server sending a label with each listing. This duplicates the date formatting in PHP, and the grouping would still need the browser.

## Risks / Trade-offs

- **[Long runs crowd today's group]** A run under way sits under TODAY every day until it ends, ordered by days left. Weekly sessions entered as one long run, with no specific days, look as if they are on daily. → This is how the rule works, not a fault in it. Giving those listings specific days in the Dashboard moves each one to its real day. The proposal notes this, and it is outside the change.
- **[A day boundary between server and reader]** A page left open past midnight, or read in another time zone, labels TODAY by the server's date. → This is consistent with the listings the server chose for that page. A reload brings both up to date.
- **[Raw SQL behaving differently on the two databases]** → Only functions both support. The feature tests run the scope on SQLite. Before deploying, run the generated SQL as a read-only query against staging, which holds a copy of live's listings.
- **[Browser tests that count rows]** `ListingsAdvertSlotsTest` maps every grid child to "listing" or "advert", and its listings all start today, so a TODAY heading now appears among them. → The script learns the heading row as a third kind, and the test asserts it. This states the new layout; it does not loosen the check.
- **[Heading levels]** Row titles are `h1`, so a day heading's `h2` sits above them in the outline. → Accepted for now. Reworking the row's own headings belongs to a separate change.

## Migration Plan

- No migration and no data changes. The change deploys with an ordinary push.
- Rollback is reverting the commit. Nothing is stored that would need undoing.

## Open Questions

- **The heading strip's exact look**, whether tinted like the search panel or plain. Settle it while building, checking light and dark. It changes neither the spec nor the tasks.
