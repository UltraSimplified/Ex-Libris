# Design

## Context

See proposal.md for why, and specs/festivals/spec.md and specs/listing-dates/spec.md for the behaviour.

- **Promoters are the template.**
  - `Promoter` uses `HasBrand`, which provides the four colours and `Brand::from()`'s fallback to Good on Paper's palette. Saving a promoter calls `WidgetCache::forgetOwner()`.
  - The logo (512 px square) and banner (1280 wide) are stored by `BrandImage`. The Backstage `PromoterController::update()` swaps and deletes them inline, and `PromoterRequest` holds the rules.
  - The Backstage form is `dashboard/promoters/Edit.vue`, built from the existing `BrandFields.vue` and `ImageField.vue`.
  - `PromoterPolicy` lets staff do everything through `before()`, lets owners edit, and refuses create and delete.
- **Listing permissions.**
  - `User::listablePromoters($keep)` limits a listing's promoter to those the account runs, plus the one the listing already names. `ListingRequest::mustBeSelectable()` re-checks that on save.
  - `User::editableListings()` also lets a venue owner edit other promoters' events at their venue.
- **Public pages.**
  - `ListingController::promoter()` renders `Listings.vue` with a `promoter` prop. `ListingsIndex.vue` draws `PromoterProfile.vue` over a list ordered by `byNextDay()` and headed by day.
  - Covers are drawn in `ListItem.vue` (the listing page), `ListingRow.vue` (lists) and `ListingsVenueIndex.vue` (venue pages). `SoldOutFlash.vue` already lays a band over them.
- **Widgets.**
  - `Widget` has a polymorphic owner: a venue, a promoter or a publisher's `User`. The owner types appear by name in `Widget`, `WidgetFeed`, `WidgetCache`, `WidgetRequest` (an `owner` regex) and the Backstage `WidgetController`.
  - `WidgetPolicy::createFor()` defers to the owner's own `update` policy.
- **Roles.**
  - `User::ROLES` lists the roles staff grant in the Office: `is_venue_mgr`, `is_promoter`, `is_writer` and `is_publisher`. `User::hasRole()` treats staff as holding all of them, and `HandleInertiaRequests` shares them with Backstage as `auth.roles`.
  - The publisher role is the closest model. It is:
    - a column added by its own migration;
    - a Boolean on `app/Nova/User.php`;
    - a `RoleList` resource (`app/Nova/Publisher.php`) in the Office menu;
    - a sidebar band shown only when `auth.roles.is_publisher` is set;
    - a check in `WidgetPolicy` through `hasRole('is_publisher')`.
- **Size limits the analyser enforces.**
  - `ListingController` (public) is already 437 lines, with placeholder methods.
  - The Backstage `ListingController` is 348 lines and `WidgetController` 240.
  - Sloppy's God Class (SL102) and Duplicate Logic (SL104) rules fail CI over the whole of `app/`, so new behaviour has to avoid both growing these classes and copying promoter code.

## Goals / Non-Goals

**Goals:**
- One rule, in one place, decides whether a listing's festival is valid, and both Backstage and the Office use it.
- Festivals reuse promoter code rather than copying it.
- Every query that draws a listing's cover loads its festival in the same query, never one query per row.

**Non-Goals:**
- Festivals on the Backstage home panels.
- Tinting any public page in brand colours.
- The festival mark on social share images or in widgets.
- Moving a festival to another promoter.
- Fixing the widget credit link, which still sends a promoter's widget to `/listings` rather than the promoter's page.

## Decisions

### A table of its own, mirroring promoters

A new `festivals` table holds `promoter_id` plus the same columns as `promoters`: name, website, email, phone, logo, banner, about (text) and the four colours. `listings` gains `festival_id` and `show_festival_mark` (boolean, default true).

- `festivals.promoter_id` is a constrained foreign key that cascades on delete.
- `listings.festival_id` is a constrained foreign key that nulls on delete. This gives the spec's "listings stay, outside any festival" at the database, whichever way a festival goes.

`Festival` uses `HasBrand`, belongs to `Promoter` and has many `Listing`s and morph-many `Widget`s. `Promoter` has many festivals. `User` gains `festivals()` as a has-many-through its promoters.

*Alternatives considered:*
- **A flag on `promoters` making some rows festivals, with a self-referencing parent.** Every promoter query, the promoter policy and the Office resource would then have to tell the two apart, and a festival would show up as a promoter wherever promoters are listed.
- **A festival as a special parent listing.** A listing has one venue, a cover and a run, none of which fit a multi-venue festival. The listing queries would also all have to skip it.

### The Runs Festivals role follows the publisher role, except for staff

- **Column and role list:** `users.runs_festivals` (boolean, default false) is added to `User::ROLES`, which carries it into `auth.roles` and `isBackstage()`. The column is named for its label rather than with the `is_` prefix the older roles use, so the code and the Office say the same thing.
- **Not implied for staff:** `hasRole()` treats staff as holding every role. A new `User::ROLES_STAFF_TICK` constant lists the roles it does not imply, starting with `runs_festivals`, so a staff account has it only when it is ticked on that account. The Office's festival screens don't depend on it, because `FestivalPolicy::before()` still lets staff through there.
- **Office:** `app/Nova/User.php` gains a "Runs Festivals" Boolean, with help text saying staff accounts need it ticked too, unlike the roles above it. `loginIsOptional()` treats it like the other dashboard roles. A new `FestivalRunner` `RoleList` joins the Office's Users menu beside Publishers, labelled Festivals, as the promoter accounts' list is labelled Promoters.

What an account runs is decided in one place. `User::managedFestivals()` returns the festivals of the account's promoters when it holds the role, and no festivals when it does not. Every Backstage path that offers or accepts a festival reads it:
- `FestivalPolicy`;
- the `auth.festivals` prop;
- the listing form's festival choices and its `?festival=` filter;
- `FestivalOfPromoter`;
- the widget owner choices.

So hiding the menu and refusing a hand-made request cannot drift apart.

*Alternatives considered:*
- **Letting every promoter account run festivals.** Rejected because the magazine chooses who runs festivals, as it does who publishes.
- **A flag on the promoter rather than the account.** Every other permission lives on the account, and the Office already has a place to grant them.

### Deleting a festival goes through the model

A `deleting` hook on `Festival` deletes its widgets and its logo and banner files. The foreign key then frees its listings. This mirrors how `User::booted()` deletes an avatar, and covers the Backstage and the Office alike.

The database cascade from a deleted promoter would skip model events. So `Promoter` gains a `deleting` hook that deletes its festivals through the model first.

*Alternative:* deleting widgets and files in each controller. Nova's delete would then leave them behind.

### One validation rule for a listing's festival

A new rule class, `FestivalOfPromoter`, passes when the submitted festival:
- belongs to the submitted promoter; and
- is either run by the account saving it, or is the festival the listing already has. The second case is the venue owner's case.

It fails when a festival is given with no promoter. `ListingRequest` uses it on `festival_id`, beside the existing `mustBeSelectable` checks. For an account that does not have Runs Festivals, the Backstage `ListingController` leaves `festival_id` and `show_festival_mark` out of what it saves, so the listing keeps both. The Office's Festival field on `app/Nova/Listing.php` uses the same rule with no account restriction, since staff run everything.

In the Office, the Festival field `dependsOn` the Promoter field. It offers only that promoter's festivals and clears when the promoter changes.

*Alternative:* a `saving` guard on `Listing` that throws. That would reach every write, but it gives a 500 instead of an error by the field.

### The listing form gets festivals inside the promoters it already receives

The Backstage `ListingController::promoters()` returns each listable promoter with the festivals the account runs (`id`, `name`), plus the listing's current festival when it is being kept. An account that does not have Runs Festivals gets none, and the form shows no Festival choice or mark tickbox when `auth.roles.runs_festivals` is unset.

- `ListingFormFields.vue` filters the Festival choice by the chosen promoter and clears it when the promoter changes.
- It shows the mark tickbox only while a festival is chosen.
- `create` reads `?promoter=` and `?festival=` to pre-select both, which is how "Add listing" from a festival's Event Listings works.

This adds no new props and almost no lines to a controller that is already large. Duplicate keeps `festival_id` and `show_festival_mark` because they are ordinary columns copied with the rest.

### Shared promoter and festival code, not copies

- **Images:** the logo and banner swap in `PromoterController::update()` moves into one method on `BrandImage`. Both the promoter and festival controllers call it. It stores, clears and deletes the replaced files only after the row saves, as now.
- **Validation rules:** the fields `PromoterRequest` validates move into a trait beside the existing `ValidatesBrandColours`. `FestivalRequest` uses that trait and adds `promoter_id`, required on create and limited to promoters the account runs.
- **Form:** the festival form reuses `BrandFields.vue` and `ImageField.vue`, as the promoter form does.

Without this, the festival controller's `update()` would be a copy of the promoter's, which Sloppy reports as Duplicate Logic.

### Backstage festival pages are a resource controller

`Dashboard\FestivalController` has index, create, store, edit, update and destroy, registered with `Route::resource(...)->except('show')`.

- **Index** lists the account's festivals, narrowed by `?promoter=` the way listings are.
- **Create** offers only the account's promoters, pre-selecting `?promoter=`.
- **Edit** shows the promoter but does not offer to change it.

`FestivalPolicy` mirrors `PromoterPolicy`, except that viewAny, create, update and delete are allowed for an account with Runs Festivals that runs the festival's promoter, through `managedFestivals()`. `before()` lets staff through, and every Nova ability is answered.

### A new festival starts from an earlier one through the create form

A promoter with an annual festival makes a new festival each year: Stroud Film Festival 2027, then 2028. Most of the details carry over, so the new festival form can start from one the account already runs.

- **Choosing:** a "Start from" select at the top of the create form, shown only when the account runs a festival. Choosing one reloads the form with `?from=`. The controller looks the festival up in `managedFestivals()` and ignores any other id, as it does `?promoter=`.
- **Filling:** the form is filled with that festival's details, colours and pictures, with the name left blank and its promoter pre-selected. The form is keyed by the starting festival, so its uncontrolled fields refill when the choice changes. The festival's id goes back in a hidden `from` field.
- **Saving:** `FestivalRequest` accepts `from` only for a festival in `managedFestivals()`. Before `saveWithPictures()` runs, `BrandImage` copies each picture the form neither replaced nor removed to a new file. The details and colours arrive as posted form fields, so nothing else is copied on the server. Listings and widgets are not copied.
- **Copies, not shared paths:** deleting a festival deletes its pictures, so a shared path would strip them from the other one. Duplicated listings get their own files for the same reason.
- **Not in the Office:** `FestivalPolicy::before()` does not let staff through for `replicate`, so Nova does not offer Replicate on festivals. Its copy would share the picture files.

Duplicating the festival straight away, as listings are duplicated, was the alternative. It would put up a half-made "Copy of …" festival the moment the button was pressed. Starting from the form saves nothing until the account saves.

### The menu reads festivals from the shared auth props

`HandleInertiaRequests` adds `auth.festivals` (`id`, `name`, `promoter_id`) next to `auth.promoters`, from `managedFestivals()`, so it is empty for an account that does not have Runs Festivals. Its `named()` helper widens from `HasMany` to also accept that query.

In `AppSidebar.vue`:
- each promoter's group gains a Festivals item, pointing at the festival index for that promoter, only when `auth.roles.runs_festivals` is set;
- `runs()` gains a `festival` key, so each festival gets a group of its own with Event Listings (`?festival=`) and Gig Widgets (`?festival=`).

The Backstage listing and widget indexes recognise `?festival=` the way they already recognise `?promoter=`, matching only festivals the account runs.

### The public festival page has its own controller

A new `FestivalController` (public) serves `/listings/festival/{id}/{slug}` by rendering `Listings.vue` with a `festival` prop and a `finished` flag. It doesn't go in `ListingController`, which is already large.

- **Which listings:** when the festival is finished, all of its listings ordered by `byNextDay($firstDay)`. Otherwise `current()->byNextDay(today())`.
- **Finished:** the festival has a listing and none is `current()`.
- **First day:** the earliest start date among its listings.
- **Day headings:** `ListingController::listingDays()` is private, so it moves to a small shared helper that both controllers call.

`ListingsIndex.vue` treats `festival` as a profile owner alongside venue and promoter. It hides the genre pills, shows the "has finished" line, and draws the header with `PromoterProfile.vue`. That component gains an optional "by" link, rather than a near-identical copy being made. `ListingController::promoter()` adds the promoter's current festivals to its props, for the strip of links.

### The mark is drawn by the browser, from data loaded with the listing

A new `FestivalMark.vue` sits absolutely in the bottom right of the cover's positioned box:
- a square tile, a third of the cover's width, in the festival's background colour;
- the logo fitted inside it, with a small inset;
- layered under `SoldOutFlash`, so the band always stays on top.

It renders only when the listing is in a festival, `show_festival_mark` is set and the festival has a logo. It is used in `ListItem.vue`, `ListingRow.vue` and `ListingsVenueIndex.vue`. It is not used in `BookmarkedListingCompact.vue`.

`Festival` appends `mark_background`: the festival's background colour, or Good on Paper's when it has none. This means the fallback lives in `Brand` alone.

The public listing queries load the festival through one `Listing` scope that eager-loads venue, category, promoter and festival (with only the columns the mark and the "Part of" line need). Every listings action switches to it from its own `with(...)` chain. That keeps the festival from being fetched one row at a time (Sloppy SL203), and replaces repeated eager-load lists with one.

*Alternative:* compositing the logo into the stored cover with Intervention. The file would go stale when the logo changed or the listing left the festival, the original would need keeping, and the cover is shared with the Office's own image pipeline.

### Festivals are a third widget owner

Each place widget code tells owners apart learns about `Festival`:
- **`Widget`:** `brand()`, a new `isForFestival()`, `spansVenues()` and `constrainListings()` on `festival_id`.
- **`WidgetCache::forgetListing()`:** the `festival_id` column. `Festival` saving forgets its owner, as `Promoter` does.
- **`WidgetFeed`:** `ownerPage()` returns the festival page. `events()` drops `current()` and orders from the first day when the festival has finished.
- **`WidgetRequest`:** the `owner` pattern.
- **`WidgetController`:** owner choices, resolving and labels. A single owner-type map replaces the separate `venue`/`promoter` branches in `resolveOwner()`, `owners()` and the request pattern, so a third type does not add a third branch to each.

`WidgetPolicy` needs no change, because it defers to `FestivalPolicy::update()`. The owner choices offer festivals from `managedFestivals()`, so an account that does not have Runs Festivals is offered none.

### Search engine data is built where promoter and listing data already is

- **`SiteMeta::festival()`:** a `Festival` with name, url, description, banner or logo image, `startDate` and `endDate` from its listings, `organizer` as the promoter's `Organization`, and `subEvent` as each listing's name, url and start date.
- **`ListingMeta`:** adds `superEvent` (the festival's name and url) to a listing in a festival.
- **`Sitemap`:** adds every festival that has a listing.

## Risks / Trade-offs

- **The mark covers poster details in the bottom right.** → It is per listing, ticked by default, and the listing preview shows it before saving.
- **Branded content goes live without review.** → It's the same trust Backstage already gives listings from approved accounts. Staff can edit or delete any festival in the Office.
- **A listing query that misses the new scope** shows covers without marks and runs a query per row. → Every action that renders covers moves to the one scope, and a feature test asserts a festival listing on each listings page carries its festival.
- **A promoter deleted in the Office** would cascade past the festival model's hooks. → `Promoter` deletes its festivals through the model first. A feature test covers the files and widgets going.
- **Widgets on other sites stop working when their festival is deleted.** → Backstage's delete confirmation says so.
- **Festival pages with no listings** are reachable but empty. → They stay out of the sitemap until they have a listing.
- **Taking the role away leaves festivals nobody can manage in Backstage.** → Staff still manage them in the Office, and the promoter's account can still edit its listings without losing their festival.
- **An account given Runs Festivals without a promoter** becomes a Backstage account with nothing festival-related to do, as a publisher with no bookmarks has nothing to show. → The Office ticks the role on promoter accounts.

## Migration Plan

1. One migration creates `festivals`, adds `festival_id` and `show_festival_mark` to `listings`, and adds `runs_festivals` to `users`. Its `down()` drops the columns, then the table. The maintainer runs it on staging and live before pushing, because the code reads the new columns. No existing rows change.
2. The routes are new, so the route cache has to be rebuilt before `npm run build`, or Wayfinder leaves out the new actions.
3. Rolling back means reverting the code and running the migration's `down()`. That loses any festivals made in the meantime, and their listings simply leave them.
