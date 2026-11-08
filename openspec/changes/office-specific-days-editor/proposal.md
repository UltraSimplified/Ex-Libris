# Proposal

## Why

Specific days can only be set in the Dashboard. The Office shows them read-only, links to the Dashboard only once a listing already has days, and greys out the dates of such a listing. Staff editing a listing in the Office have to leave it for another screen to say when the event is on, and a listing with no days yet has no link at all. The Office should edit specific days as the Dashboard does.

## What Changes

- The Office listing form gains the same "Set specific days" calendar as the Dashboard, just after the dates:
  - offered only once the end date is after the start
  - Select all and Clear all
  - Mon to Sun weekday chips
  - a count of the days chosen
  - days outside a shortened run held back, with a note
- It is drawn in the Office's own colours. Ticked days and chips that are on use Nova's primary colour, which Goblin mode turns green, so the Office stays in one shade there.
- Saving in the Office follows the Dashboard's rules: the same checks and messages, the run pulled in to the first and last chosen day, one chosen day saved as a one-day listing, and every day chosen saved as no specific days.
- **BREAKING (Office behaviour):** while a listing has specific days, its start and end dates are no longer read-only in the Office, and the "Edit days in the Dashboard" link goes. The listing's detail page still lists its days.
- Replicate in the Office opens with the original's days ticked, so they are copied by saving the form. The separate copying after the copy is created goes. Moving the copy's dates past every ticked day now asks for days, or for "Set specific days" to be unticked, as the Dashboard does. Before, the copy was saved with no days.
- The Dashboard picker's behaviour moves into a piece shared by both calendars, so they cannot drift apart. Nothing changes for Dashboard users.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `listing-dates`:
  - The Office now edits specific days instead of showing them read-only.
  - Office dates stay editable for listings with specific days.
  - Office duplication copies days through the Replicate form, and the moved-past-every-day case now follows the Dashboard's validation.

## Impact

- **Mostly the Office.** It gains a new field; its read-only dates, Dashboard link and copying on Replicate go. The Dashboard's picker is refactored onto the shared piece without changing what users see.
- **No migration and no live data changes.** It uses the existing `listing_days` table.
- **A new local package**, `nova-components/SpecificDays`, built like `VideoEmbed` and `Gallery`. This adds a path repository and a `require` to `composer.json` and updates `composer.lock`, plus a `build:nova-specific-days` npm script. CI already installs these packages and copies `nova-components` to the servers, so the deploy itself is unchanged.
- **Code:**
  - `app/Nova/Fields/ListingDateFields.php` and `app/Nova/Listing.php`: the field goes in; `afterCreate` and the read-only logic come out.
  - `app/Services/Listings/ListingDays.php`: the day-writing step is split out so Nova can call it after its own save.
  - A validation rule for the Office's days.
  - `resources/js/components/dashboard/SpecificDaysPicker.vue`: moves onto a new shared composable.
  - Tests in `tests/Feature/NovaListingTest.php` and `tests/Browser/OfficeListingSaveTest.php`.
