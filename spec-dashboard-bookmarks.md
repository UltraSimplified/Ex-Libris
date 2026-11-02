# Spec: Bookmarked listings panel on the Dashboard

**Status:** for Robin's approval, 2 November 2026. Nothing is built yet.

## Summary

Add a "Bookmarked listings." panel to `/dashboard`. It shows the account's bookmarked listings that have not finished, using the same row as the listings pages. Under each row sit a checkbox ("Tickets purchased", or "Going" for a free event), the listing's own ticket link, copy link, WhatsApp and Add to calendar.

**Most of this is small.** The row, the heart, the "not finished yet" filter and the listings order already exist and can be reused unchanged.

**Calendar subscription is possible and not over-complicated. You've approved it** as a second release once the panel is live. It needs one new column, one read-only address and one reset action, and it reuses the file built for Add to calendar. It has limits on Android and in Google Calendar, covered in [section 7](#7-subscribable-calendar).

**Three things in the code change what you might expect:**

1. **Readers don't reach the Dashboard today.** `HomeController` sends anyone without a role to the home page. You've decided readers with bookmarks should get it, so the redirect and the site's Account links change. See [section 1](#1-who-sees-the-panel-and-when).
2. **Listings have no real start time.** `event_time_description` is free text, such as "Doors 7pm, Start 8pm" or "Times vary". Calendar entries are therefore all-day, with the time in the notes.
3. **Most listings don't sell tickets as such.** Of the 191 current listings in `devgop_data`, 151 have the button text "Find out more" and only 15 say "Buy tickets" or "Book Tickets". Free events therefore get their own checkbox wording. See [section 4](#which-label).

## Decisions

Robin's answers, 2 November 2026:

1. **Readers with bookmarks get the Dashboard.** A reader with at least one current bookmark stays on `/dashboard`, and a reader with none still goes to the home page. This reverses a deliberate rule, and the changes are listed in [section 1](#1-who-sees-the-panel-and-when).
2. **The checkbox appears on every listing**, labelled "Tickets purchased", or "Going" on a free event.
3. **Calendar entries are all-day for now.** A wrong time in someone's calendar is worse than none. A cautious parser could be added later for clear cases like "7pm" and "Doors 7pm, Start 8pm".
4. **Clicking a listing's row opens the event.** The strip of controls beneath it doesn't, so a slightly missed tap on the checkbox can't open another page.
5. **`/dashboard/bookmarks` is removed.** Changed on 2 November 2026, once the panel was live: the sidebar's My Bookmarks now scrolls to the panel, and is hidden with nothing saved. The old address redirects to the panel. `/listings/bookmarked` stays.
6. **The title is "Bookmarked listings." with a full stop.** It will be the only Dashboard panel title with one.
7. **Cloudflare has no "Cache Everything" rule and Bot Fight Mode is off.** Read from the zone's dashboard, which replaces the earlier answer that one or both were set. Browser Integrity Check is on, so it's the one thing to test. See [Cloudflare](#cloudflare).
8. **The subscription goes ahead as a second release.**

## 1. Who sees the panel, and when

**The panel shows when the account has at least one bookmark whose listing has not finished.** It comes after the account panel and any venue and promoter panels, so it is literally third only for an account running one thing.

**If every bookmark is in the past, the panel is hidden**, with no empty state. This is rare: `bookmarks:prune --commit` deletes those bookmarks at 03:20 each day, so the state normally lasts only from midnight until then.

**Guests never see it.** `/dashboard` requires a login. Bookmarks saved in a browser's cookie join the account on the first request after login, through `MergeGuestBookmarks`, so they appear in the panel straight away.

### Readers

A reader with a current bookmark gets the Dashboard: their avatar or initials, the account panel, then this panel. The sidebar already suits them, showing Good On Paper, My Bookmarks, Profile, Support and Log out, as the venue and promoter sections only appear for accounts that run something.

**The rule:** a new `User::canOpenDashboard()` returns true when `hasDashboard()` is true or the account has a current bookmark.

| Where | Today | Change |
|---|---|---|
| `HomeController` | Redirects when `hasDashboard()` is false | Redirects when `canOpenDashboard()` is false |
| `HandleInertiaRequests` | Shares `auth.roles` | Also shares `auth.canOpenDashboard`, as a closure like its neighbours |
| `Header.vue`, desktop | Dashboard link only for accounts with a role | Reads the new prop |
| `Footer.vue` | Readers get "Log out" in place of "Account." | Reads the new prop, so these readers get "Account." and log out from the sidebar, as accounts with a role do |
| `Header.vue`, phone menu | "Account." always links to `/dashboard/` | No change: it now reaches the panel for these readers |
| `User::hasDashboard()` | Roles only | **No change** |

**`hasDashboard()` stays as it is because `canBeImpersonated()` relies on it.** Changing it would let staff switch into any reader's account as soon as they bookmarked something.

**What readers will notice:**

- **Login:** a reader with a current bookmark lands on the Dashboard rather than the home page, because Fortify's home is `/dashboard`. Bookmarks saved before logging in count, as `MergeGuestBookmarks` runs first.
- **The first bookmark:** the Dashboard link appears on the next page load, not the moment the heart is tapped, because `useBookmarks` doesn't reload the page's props.
- **The last bookmark:** when it's removed or its event finishes, the reader goes back to being sent to the home page, and the footer goes back to "Log out". A reader who empties the last heart in the panel stays on the page until they leave it.

**Comments to correct**, as they state the old rule: `HomeController`, `routes/web.php` above the `dashboard` route, `Header.vue` and `Footer.vue`.

**Existing tests keep passing**, because their readers have no bookmarks: the "a reader…" tests in `DashboardTest.php` and `FooterAccountLinkTest.php`. New tests cover readers with bookmarks.

## 2. Data

### The checkbox's value

Store it on the bookmark, because it describes one person's relationship with one event. "Tickets purchased" and "Going" are two labels for the same tick, so the column has a neutral name.

```php
// Migration, on the bookmarks table
$table->boolean('attending')->default(false)->after('listing_id');

// down()
$table->dropColumn('attending');
```

Add `->withPivot('attending')` to `User::bookmarks()`.

- **Saving a bookmark twice keeps the tick.** `syncWithoutDetaching()` only updates an existing row when given attributes, and `AccountBookmarks` passes none. The guest merge is safe for the same reason.
- **Removing a bookmark discards the tick**, because the row goes. So does the nightly prune, which doesn't matter once the event is over.
- **Guests have no tick.** The panel is account-only, so `GuestBookmarks` and the `BookmarkStore` interface don't change.
- **The value arrives as 0 or 1**, because pivot columns aren't cast. The front end wraps it in `Boolean()`.

**Run the migration on live before deploying the code.** Once `withPivot` is in place, any `->get()` on the relation selects the new column, so the panel would return a 500 without it. Ex-Libris migrations don't reach `gop_livedb` on their own.

### Which listings, in what order

```php
$user->bookmarks()
    ->with(['venue', 'category', 'promoter'])
    ->current()
    ->finishingSoonest()
    ->get()
    ->each->append(['ticket_link', 'is_free']);
```

This goes in a private method on `HomeController`, passed as the prop `bookmarkedListings`.

- **"Today and future" is the existing `current()` scope**, which keeps a listing until the last day it runs. A run that began last week and ends next week is shown, which I take to be what you want.
- **"Today" means Europe/London.** `config/app.php` sets that timezone directly, and `today()` follows it. The prune command compares dates the same way, so the two can't disagree.
- **A listing stays until midnight on its last day**, even after the event has ended, because there's no reliable finish time.
- **The order is `finishingSoonest()`**, the same as the listings pages and `/listings/bookmarked`.
- **Whole models are sent**, as `/listings/bookmarked` does, so `ListingRow` needs no change. The extra weight is small: `devgop_data` has at most 2 bookmarks per account, though I haven't checked live.
- **There's no limit on the list.** A row is tall on a phone, so a long list makes a long page. Revisit this if real accounts build up long lists.

## 3. The panel

```text
┌──────────────────────────────────────┐
│ Bookmarked listings.                 │
├──────────────────────────────────────┤
│ [ image, full width ]                │
│ [MUSIC] ♥                            │  ListingRow, unchanged:
│ FRI 14 NOV                           │  a click here opens the
│ Band name                            │  event page
│ TIME: 7PM   PRICE: £12               │
│ ⚲ VENUE                              │
├╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌┤
│ ☐ Tickets purchased  (or ☐ Going)    │  Controls strip, outside
│ ( FIND OUT MORE )                    │  the row's link
│ Copy link   WhatsApp                 │
│ (+) Add to calendar                  │
├──────────────────────────────────────┤
│ next listing…                        │
├──────────────────────────────────────┤
│ Your calendar  (section 7)           │
└──────────────────────────────────────┘
```

- **The layout is one column, the same at every width.** Nothing is hidden on phones. On wider screens `ListingRow` moves its image to the side, as it does on the listings pages.
- **The panel has no padding of its own around the rows**, unlike the other panels' `p-6`, because `ListingRow` brings its own and would otherwise be inset twice. The panel uses `overflow-hidden` so the row's hover colour stays inside the rounded corners.
- **The title is an `h2`** reading "Bookmarked listings.", with the same classes as the other panels' titles.
- **Check it at about 768px with the sidebar open.** The `md:` layout follows the window width, not the panel's, so the text column is at its narrowest there.

### Clicking through to the event

`ListingRow` already makes the whole row a link: the title's link is stretched across the row, and the category, heart and venue sit above it. The controls strip goes **after** the row, outside its `relative` root, so the stretched link can't reach it. `ListingRow` itself doesn't change.

The back link on the event page uses browser history, so it returns to the Dashboard.

**Staff also see the Office pencil in each row**, because `ListingRow` includes `OfficeEditLink`. That's existing behaviour and is left as it is.

## 4. Un-bookmarking and the checkbox

### Removing a bookmark

The heart is `BookmarkHeart`, unchanged, calling the existing `DELETE /listings/{listing}/bookmark`.

- **The row stays on screen with an empty heart until the next visit.** `/listings/bookmarked` already behaves this way, and it gives a free undo for a mistaken tap.
- **While the heart is empty, the checkbox and ticket link are hidden**, as there's no bookmark to record against. The share and calendar links stay.
- **Re-bookmarking brings the checkbox back unticked**, as the tick went with the old bookmark. There's no "are you sure?" prompt, because a tick is easy to redo.
- **The panel stays until the next visit**, even if every heart is emptied.

### The checkbox

Uses the existing `components/ui/checkbox`, with the whole label tappable.

#### Which label

**"Going" when the listing is free, otherwise "Tickets purchased".** `ticket_price` is free text, so a new `is_free` accessor on `Listing` decides, with one rule: **the price contains the whole word "free", in any case, and no digit.** The digit test stops a paid event with a free concession counting as free.

Applied to the 191 current listings in `devgop_data`:

| `ticket_price` | Label | Listings |
|---|---|---|
| "Free", "Free!", "Free entry", "Admission free, donations welcome", "Admission free, artwork and books for sale" | Going | 17 |
| "£10 (adult), £6 (concession), children go free", "£18.00 - free for carers and 18 and under" | Tickets purchased | 2 |
| "Pay What You Feel", "Pay What You Can: £8-£15" | Tickets purchased | 4 |
| Empty | Tickets purchased | 25 |

- **An empty price isn't assumed to be free**, because it may simply not have been entered.
- **A mistake only costs a slightly wrong label.** For example, "Free, £5 donation suggested" would read "Tickets purchased".
- **The label follows the current price.** If a free event starts charging, a tick stays and the label changes.

#### Saving it

```text
PUT    /listings/{listing}/attending   ->  { "attending": true }
DELETE /listings/{listing}/attending   ->  { "attending": false }
```

- **Two verbs, not a toggle**, to match `ListingBookmarkController`, so a double tap or a flaky connection can't leave it the wrong way round.
- **Routes use `auth` and `throttle:60,1`.** The logic goes on `AccountBookmarks`, in a method that isn't on the `BookmarkStore` interface.
- **A listing the account hasn't bookmarked returns 404.** Check that the bookmark exists first, rather than relying on `updateExistingPivot()`'s return value: MySQL can report 0 changed rows when the value is already set, which would look like a missing bookmark.
- **The tick shows immediately and reverts if the request fails**, as `useBookmarks` does for the heart.
- **`useBookmarks.ts` needs a small tidy.** Its private `csrfToken()` becomes shared, and the comment saying nothing else posts outside Inertia gets corrected.

### Keeping state correct on Back

**This is a trap.** Inertia restores a page's old props from browser history, so going to the event page and back would show the checkbox as it was when the page first loaded.

After each successful request, update the stored page with `router.replaceProp()` (present in the installed `@inertiajs/vue3` 2.3.29):

- The checkbox updates its entry in `bookmarkedListings`.
- `useBookmarks.toggle()` updates the shared `bookmarks` prop.

From reading the code, the heart should have the same stale-on-Back problem on every page today, though I haven't reproduced it. The fix to `toggle()` is one line and would cover them all. A browser test covers both.

### The ticket link

**Shown only when the box is unticked**, whichever label it has. It uses the listing's own `button_text` as its wording, in the same hi-vis pill as the event page, so most will read "Find out more". Once ticked, the details are still a click away through the row.

- **The address is `ticket_link`**: the event's link, or failing that the venue's website, tagged with Good on Paper's referral details as on the event page.
- **It's hidden if `ticket_link` is empty**, whereas the event page would draw a button that goes nowhere.
- **It still shows on a sold-out listing**, as it does on the event page. The row carries the sold-out flash.
- **It opens in a new tab with `rel="noopener noreferrer nofollow"`.** The event page has `noreferrer` and `nofollow` as bare attributes, which do nothing. That's worth a separate small fix.

## 5. Sharing

Both links share the public address, built from the current site address plus `/listings/{id}/{slug}`. It carries no tracking and nothing about the user.

### Copy link

Uses `navigator.clipboard.writeText()`, as the widget editor does, and the label changes to "Copied" for two seconds, also as it does there.

- **Phone and desktop behave the same.**
- **Clipboard access needs HTTPS.** Live is always HTTPS, but the call can still fail, so it needs a `catch` that shows the address to copy by hand. The widget editor has no such fallback.

### Share to WhatsApp

A plain link, opening in a new tab:

```text
https://wa.me/?text=<URL-encoded "Band name at Venue, FRI 14 NOV https://…">
```

- **On a phone**, it should open WhatsApp at the contact picker, with the message ready to send.
- **On a computer**, it should go through WhatsApp's own page to the desktop app or WhatsApp Web.
- **Nothing sends until the user presses send.**
- **The message includes a preview.** `app.blade.php` writes `og:title`, `og:description` and `og:image` into the page's HTML, so WhatsApp can build a preview without running JavaScript.
- **Use `wa.me`, not `whatsapp://`**, which does nothing on a device without the app.

WhatsApp's own documentation I could find only covers the form with a phone number. The form without one is widely used but only confirmed by secondary sources, so it's on the [device checklist](#device-checklist). If it fails, `https://api.whatsapp.com/send?text=` is the fallback.

The lucide icon set has no WhatsApp logo, so this needs a small inline SVG.

## 6. Add to calendar

**"Generic" realistically means an `.ics` file**, the one format every calendar app understands. There's no single web link that adds an event to whichever calendar someone uses.

Shown as a `+` on a `bg-hivis` disc with the words "Add to calendar". That colour is yellow on the main site. It's a plain `<a>`, not an Inertia `<Link>`.

```text
GET /calendar/listings/{listing}.ics
```

### What's in the file

| Field | Value |
|---|---|
| `SUMMARY` | Listing title |
| `DTSTART;VALUE=DATE` | `start_date` |
| `DTEND;VALUE=DATE` | The day after the last day, as the standard treats the end as exclusive |
| `LOCATION` | Venue name, address, town and postcode, skipping blanks |
| `DESCRIPTION` | Time text, date notes, price, and the Good on Paper link |
| `URL` | The Good on Paper event page |
| `UID` | `listing-{id}@goodonpaper.info`, stable, so apps can match the same event |

**Why all-day:** `event_time_description` is free text, and "Times vary" or "Thurs - Saturdays 10am - 3pm" can't be turned into a time. All-day dates also avoid any timezone handling. In `devgop_data`, 27 of 191 current listings have no time at all.

**A consequence for long runs:** 53 of 191 current listings run over several days. They appear as a long banner across those days, so "Thursdays only" for five weeks shows as five solid weeks, with the note in the description.

**A copy doesn't follow changes.** If the date moves after the event is added, the entry stays as it was. Subscribing is what stays up to date.

**No new dependency.** `composer.json` has no calendar library. A small class in `app/Support`, about 60 lines, covers what's needed: escaping, 75-byte line folding and CRLF line endings. A library would need your approval and wouldn't save much.

### On each device

| Device | Expected behaviour | Confidence |
|---|---|---|
| iPhone, Safari | Opens a sheet offering to add the event | Good, though from forum and vendor sources |
| iPhone, Chrome | May fail or only offer a download | **Reports of failure, mostly old. Must test.** |
| Android, Chrome | Downloads the file or offers a choice of calendar apps, depending on what's installed | **Unconfirmed. Must test.** |
| Mac or Windows | Downloads the file, then opens the default calendar app | Good |

**If Android or Chrome on iPhone fails in testing**, add a fallback rather than change the design:

- **Android:** a Google Calendar "create event" link.
- **Chrome on iPhone:** the same file over `webcal://`, which sources suggest may work.

### Route setup

These routes go in a new `routes/calendar.php`, registered in `bootstrap/app.php` the same way as `routes/embed.php`: outside the `web` group, so no session or cookies.

- **`calendar/*` also needs adding to the exception handler's `embed/*` check**, so a 404 is a plain response and not the site's Inertia page.
- **The path deliberately avoids `/listings/…`**, where `listings/{id}/{slug}` would catch it first.
- **Send `Content-Type: text/calendar; charset=utf-8`** and a filename made from the slug.

## 7. Subscribable calendar

**Verdict: possible and not over-complicated. Include it, as a second release.** Waiting means the `.ics` file has been proved on real phones first, and the panel doesn't depend on it.

```text
GET  /calendar/feed/{token}.ics              the feed, no login
POST /dashboard/calendar-feed/reset          new token, auth required
```

### The token

- **Column:** `users.calendar_token`, nullable and unique. Made with `Str::random(40)`, following `Widget::freshKey()`.
- **Created when first needed:** the first time the panel renders for that account, so unused accounts never get one.
- **It must be added to `User::$hidden`.** `HandleInertiaRequests` shares the whole user model as `auth.user` on every page, so without this the token would be in the HTML of every page that person loads. A test guards it.
- **The address reaches the page only as a Dashboard prop.**

### What the feed reveals

**Anyone with the address can read it without logging in**, so it's limited to this:

- The same listing fields as the single-event file, all of it already public.
- A fixed calendar name, "Good on Paper bookmarks".
- **Nothing else:** no name, no email, no `attending` flag.

**Other rules:**

- **It holds the panel's listings**, meaning current bookmarks only. **Finished events disappear from the subscribed calendar**, because they leave the set and the prune deletes them. Keeping a history would mean changing the prune.
- **An empty feed returns a valid empty calendar with a 200**, not a 404, so calendar apps don't report an error. An unknown or reset token returns 404.
- **The response sends `Cache-Control: no-store` and `X-Robots-Tag: noindex`.** Add `Disallow: /calendar/` to `robots.txt`, which has uncommitted changes from other work at the moment.
- **Throttle by token, not IP.** Google's fetchers share addresses across all their users, so an IP limit like `throttle:60,1` could block innocent feeds.

### Reset

"Reset link" sits in the panel's calendar block, with a `window.confirm()` as the widget editor uses for deletion. It should warn that any calendar already subscribed will stop updating, and that the old entries stay until removed by hand.

**While staff are switched into an account** (`nova_impersonated_by` in the session), the whole block is hidden and no token is created, so staff don't see or generate someone's private address.

If someone has removed all their bookmarks, the panel and its reset link are hidden, but the feed is empty at that point. Bookmarking again brings the reset back.

### What the panel shows

The same at every width, without guessing the device:

- **Subscribe** — the `webcal://` address, for Apple Calendar and Outlook.
- **Add to Google Calendar** — `https://calendar.google.com/calendar/render?cid=…`.
- **Copy address** — the `https://` form, for pasting by hand.
- **Reset link**
- One line of help: "Google Calendar on Android: open this link once on a computer, and it will then appear on your phone."

### Limits to accept

| Calendar | Subscribing | How often it refreshes |
|---|---|---|
| Apple | One tap from a link | User's setting on Mac, from every 5 minutes to weekly. On iPhone it follows the fetch settings. |
| Google | **Computer browser only.** Google's help says it can't be done in the phone app. It then shows on the phone. | Not stated by Google, and can't be forced. Secondary sources say roughly 12 to 24 hours. |
| Outlook | Via web or desktop | Hours, on Microsoft's schedule. Sources vary. |

- **A new bookmark can take up to a day to reach Google Calendar**, and nothing on our side can change that. `REFRESH-INTERVAL` and `X-PUBLISHED-TTL` can be set to 6 hours, but clients may ignore them.
- **Google's help says linked calendars must be "public".** A working address needing no login should qualify, but it's on the checklist.
- **`webcal://` may be fetched over plain HTTP first** by some clients, exposing the token before the redirect to HTTPS. I couldn't confirm which clients do this. The data is low-risk and the link can be reset, so I don't think it should block this, but it's worth checking in the access logs.

### Cloudflare

- **Default caching is not a problem.** `.ics` isn't on Cloudflare's list of default-cached extensions, and `/listings` on live currently returns `cf-cache-status: DYNAMIC`, with no sign of LiteSpeed page caching.
- **The zone's settings on 2 November 2026** (Free plan, read from the dashboard, nothing changed):

  | Setting | State | Effect on `/calendar/*` |
  |---|---|---|
  | Cache Rules | None | None |
  | Page Rules | None | None |
  | Caching Level | Standard | None: `.ics` isn't cached by default |
  | Bot Fight Mode | **Off** | None |
  | Custom security rules | None | None |
  | Rate limiting rules | None | None |
  | Browser Integrity Check | **On** | **Could block calendar servers. Test it.** |

- **No Cloudflare rule is needed for caching.** `no-store` stays as a safeguard.
- **Browser Integrity Check is the one risk.** It judges a visitor by its HTTP headers, and calendar servers aren't browsers. A spot check of `robots.txt` with a calendar-style user agent and with an empty one both returned 200, but that's a static file, and my user agent only imitated a calendar server. **Test the real address from Apple and Google calendars after deploying.**
- **If it blocks them**, add a Configuration Rule turning it off for `/calendar/*`. The Free plan offers these, and the setting links to creating one.
- **If Bot Fight Mode is ever turned on, this needs rechecking.** Cloudflare says it [can't be skipped for a path](https://developers.cloudflare.com/bots/get-started/bot-fight-mode/), so if it challenged calendar servers the only choices would be turning it off or dropping the subscription. Super Bot Fight Mode, which can be skipped, needs a paid plan.
- **The bare domain redirects to `www.goodonpaper.info`.** Build feed addresses from `route()`, and check that live's `APP_URL` is the `www` form, or every calendar fetch starts with a redirect.

## Files

| Area | File | Change |
|---|---|---|
| Data | New migration | `bookmarks.attending` |
| | `app/Models/User.php` | `withPivot`, `canOpenDashboard()` |
| | `app/Models/Listing.php` | `is_free` accessor |
| | `app/Services/Bookmarks/AccountBookmarks.php` | Set the `attending` flag |
| Back end | `app/Http/Controllers/Dashboard/HomeController.php` | `bookmarkedListings` prop, and the redirect |
| | `app/Http/Middleware/HandleInertiaRequests.php` | Share `auth.canOpenDashboard` |
| | New `ListingAttendingController` | The two checkbox actions |
| | `routes/web.php` | Checkbox routes |
| Calendar | New class in `app/Support` | Builds the `.ics` text |
| | New `CalendarController` | The single-event file |
| | New `routes/calendar.php`, `bootstrap/app.php` | Session-free route group and the 404 check |
| Front end | `resources/js/pages/Dashboard.vue` | Add the panel |
| | New `GOP/BookmarkedListings.vue` | The panel |
| | New `GOP/BookmarkedListingActions.vue` | The controls strip |
| | `resources/js/composables/useBookmarks.ts` | Share `csrfToken()`, add `replaceProp` |
| | `resources/js/types/global.d.ts` | Add `pivot` to `Listing` |
| | `resources/js/components/GOP/Header.vue`, `Footer.vue` | Read `auth.canOpenDashboard` for the Dashboard and Account links |
| Second release | New migration, `User::$hidden` | `users.calendar_token` |
| | New `CalendarFeedController` | Feed and reset |
| | `public/robots.txt` | Disallow `/calendar/` |

`ListingRow.vue` and `BookmarkHeart.vue` don't change.

**Build and deploy notes:**

- Run `route:cache` after adding routes, before `npm run build`, or the build drops the new Wayfinder actions.
- Assets are built locally, as the server has no node.
- Run `vendor/bin/sloppy diff main --fail-on=high` and `vendor/bin/pint --dirty --format agent` before finishing.

## Tests

### Feature: `DashboardTest.php` and a new `DashboardBookmarksPanelTest.php`

- Current bookmarks reach the page in `finishingSoonest()` order, each with `ticket_link`, `is_free` and its tick.
- These all count as current: a listing ending today, one with no `end_date` starting today, and a run already under way.
- An account with only past bookmarks, or none, gets an empty list.
- Another account's bookmarks never appear.
- A reader with a current bookmark gets the Dashboard, and a reader with only past bookmarks, or none, goes home.
- A reader with a current bookmark logs in to the Dashboard, including when the bookmark came from their browser's cookie.
- `auth.canOpenDashboard` is true for a role or a current bookmark, and false otherwise.
- **`hasDashboard()` and `canBeImpersonated()` stay false for a reader with bookmarks.**

### Unit: `is_free`

- Free: "Free", "free!", "Free entry", "Admission free, donations welcome".
- Not free: "£10 (adult), £6 (concession), children go free", "£18.00 - free for carers and 18 and under", "Pay What You Feel", "Freedom Festival £5", an empty price and a null price.

### Feature: the checkbox

- `PUT` and `DELETE` set and clear the flag, and repeating either gives the same answer.
- A listing not bookmarked returns 404, including when the flag would be unchanged, and creates no row.
- A guest is refused.
- One account can't change another's flag.
- Re-bookmarking keeps a tick. Removing and re-adding clears it. A guest merge doesn't reset it.

### Unit and feature: the `.ics` file

- Single-day and multi-day listings get the correct exclusive `DTEND`.
- Commas, semicolons and line breaks are escaped, and long lines fold at 75 bytes without splitting a multi-byte character. Use a title with an accent or an emoji.
- A listing with no venue, time or price leaves out what's missing without error.
- The response has the right `Content-Type` and sets no cookie.
- An unknown listing returns a plain 404.

### Feature: feed (second release)

- A valid token returns that account's current bookmarks only.
- The response doesn't contain the account's name or email, or the `attending` flag.
- An empty set returns a valid calendar with a 200.
- Unknown and reset tokens return 404.
- No feed response sets a cookie.
- **`auth.user` never contains `calendar_token`.**
- Reset requires login and changes the token, and the old address stops working.
- While switched into an account, no token is created and no address is sent.

### Browser: Pest browser plugin

- Clicking a row opens the event page, and clicking the checkbox or share links doesn't.
- A free listing's checkbox reads "Going", and a paid one's reads "Tickets purchased".
- Ticking hides the ticket link, and unticking brings it back.
- **Tick, open the event, go Back: the box is still ticked.** The same test for an emptied heart.
- A failed request reverts the tick.
- Emptying the heart hides the checkbox and leaves the row.
- A reader with a current bookmark sees the footer's "Account." link and reaches the panel from it, and a reader without one still sees "Log out".
- Layout check at phone width, light and dark, with no JavaScript errors.

## Device checklist

These can't be proved from code, and my sources for several are forum or vendor pages. Please check them on real devices before release:

| Check | iPhone Safari | iPhone Chrome | Android Chrome | Desktop |
|---|---|---|---|---|
| Add to calendar opens a usable add step | ☐ | ☐ | ☐ | ☐ |
| WhatsApp opens with the message ready | ☐ | ☐ | ☐ | ☐ |
| WhatsApp shows the preview image | ☐ | ☐ | ☐ | ☐ |
| Copy link pastes correctly | ☐ | ☐ | ☐ | ☐ |
| `webcal://` subscribes (second release) | ☐ | ☐ | n/a | ☐ |
| Google Calendar subscribes and appears on the phone (second release) | n/a | n/a | ☐ | ☐ |

## Release order

1. **First release:** the panel, checkbox, sharing and Add to calendar. Run the `attending` migration on live, then deploy.
2. **Device checks:** add any fallbacks they call for.
3. **Second release:** the subscription. Run the `calendar_token` migration on live, deploy, then test the real address through Cloudflare from Apple and Google calendars. Add the Browser Integrity Check rule only if that test fails.

Rolling back is simple: both migrations only add one column.

## Not included

- Reminders for bookmarked events. `settings/Notifications.vue` already describes alerts for bookmarks, which is separate work.
- Native phone share sheets (`navigator.share`), as you asked for dedicated links.
- Bookmark history after an event ends.

## Sources

Code and data claims were checked against this checkout and read-only queries on `devgop_data` on 2 November 2026. Live's data may differ. These cover outside behaviour:

- [Google Calendar help: subscribing by URL](https://support.google.com/calendar/answer/37100)
- [Cloudflare: default cache behaviour](https://developers.cloudflare.com/cache/concepts/default-cache-behavior/)
- [Cloudflare: Bot Fight Mode](https://developers.cloudflare.com/bots/get-started/bot-fight-mode/) and [Super Bot Fight Mode](https://developers.cloudflare.com/bots/get-started/super-bot-fight-mode/)
- [Inertia v2: manual visits and prop helpers](https://inertiajs.com/docs/v2/the-basics/manual-visits)
- Secondary sources, so treat as indicative:
  - [Nocal: why subscribed calendars don't update instantly](https://nocal.app/help/ics-and-subscriptions/why-subscribed-calendars-dont-update-instantly)
  - [Supportability: Google Calendar on Android](https://help.supportability.com.au/article/241-roster-sync-issue-with-android-devices-google-calendar-not-updating)
  - [Tithely: ICS and webcal handlers](https://support.tithe.ly/hc/en-us/articles/30782067412247)
  - [Unipile: wa.me links](https://www.unipile.com/de/?p=275019)
  - [Stack Overflow archive: `.ics` on Chrome for iPhone](https://kiwix.lema.org/stackoverflow.com_en_all_2023-11/questions/15972628/ics-file-download-fails-on-iphone-chrome-with-unknown-file-type)
