# Proposal

## Why

Some promoters run festivals: a set of events across several venues over a few days, under a name and look of their own. Dobunni, for example, is run by Dialect but is known by its own pink and blue, which Dialect's own branding does not use. Today the site can only file those events under the promoter. There is nowhere to show the festival as one programme, in its own branding, or to say on each event that it is part of it.

## What Changes

- **A new Festival**, belonging to one promoter. It has everything a promoter has: name, about, website, email, phone, logo, banner and the four brand colours. These are the festival's own and are never taken from its promoter. A colour left unset falls back to Good on Paper's palette, as a promoter's does.
- **A Runs Festivals role, granted in the Office.** Staff tick "Runs Festivals" on an account, as they already do for the publisher role. Unlike the other roles, staff do not hold it automatically: they tick it on their own account if they want festivals in their Backstage. The Office's festival screens stay open to staff either way.
  - Only an account with Runs Festivals that runs a promoter can create and manage festivals.
  - Any other account sees nothing about festivals in Backstage: no menu entries, no Festival choice or mark tickbox on the listing form, and no festival widgets.
  - Saving a listing without the role keeps any festival the listing is already in.
  - Taking the role away changes nothing on the public site.
- **Accounts with Runs Festivals create, edit and delete their festivals in Backstage.** This is the first branded thing an account can create for itself. Approved accounts already publish listings with no review queue, and festivals go up the same way. Deleting a festival leaves its listings in place, no longer in any festival.
- **A new festival can start from an earlier one.** A promoter with an annual festival, such as Stroud Film Festival 2027 and then 2028, chooses last year's on the new festival form. The form is filled with its details, colours, logo and banner, and the account gives it a new name. The new festival gets its own copies of the pictures, and nothing on the earlier festival changes.
- **Only the festival's own promoter's listings can be in it.** A listing joins through a Festival dropdown on the listing form, which offers only the festivals of the listing's promoter. The same rule holds in the Office.
- **The festival's mark on the cover.** Each listing in a festival has a "Show the festival mark on the cover" tickbox, ticked by default. When ticked, and when the festival has a logo, the logo is drawn in the bottom right of the listing's cover image. It sits on a tile in the festival's background colour, a third of the cover's width. This applies on the listing's page and in lists of listings. It is drawn over the image in the browser, so the stored image is untouched.
- **"Part of …" on each listing in a festival.** A line under the promoter's name links to the festival's page.
- **A public festival page** at `/listings/festival/{id}/{slug}`. It is laid out like a promoter's page:
  - **Header:** the festival's banner and details, plus a "by" link back to the promoter. Its colours are not used on the page, which keeps the magazine's look, as promoter and venue pages do.
  - **Programme:** its events, headed by day.
  - **Once the festival is over:** the page shows the whole programme under a line saying it has finished, rather than going blank.
- **A promoter's page lists its festivals** that are still to come or running.
- **Gig widgets for festivals**, painted in the festival's colours. Like the page, a festival's widget shows the whole programme once the festival is over.
- **Backstage menu:**
  - Each promoter gains a Festivals entry, which lists that promoter's festivals and offers "New festival".
  - A Festivals section lists each festival the account runs, with its Event Listings and Gig Widgets.
- **Search engines:** the festival page describes itself as a schema.org `Festival` made up of its events. Each listing in a festival names the festival as its `superEvent`. Festival pages join the sitemap.
- **The Office** gains a Festival resource. The Listing resource gains the Festival field and the mark tickbox, and the Promoter resource lists its festivals.

## Capabilities

### New Capabilities

- `festivals`: what a festival is and who owns it; the Runs Festivals role that lets an account manage festivals; creating, editing and deleting festivals in Backstage and the Office; which listings may belong to one; the festival mark and "Part of" line on listings; the festival page; festivals on the promoter's page; festival gig widgets; and festival search-engine data.

### Modified Capabilities

- `listing-dates`: the requirement "Run-wide listings pages show a listing until its last day" gains the festival page. The festival page shows the whole programme once the festival is over, where every other run-wide page drops a listing once its last day has passed.

## Impact

- **Touches both the Dashboard (Backstage) and the Office.**
- **Adds a migration:**
  - a `festivals` table;
  - two new columns on `listings`: `festival_id` (nullable) and `show_festival_mark` (default true);
  - one new column on `users`: `runs_festivals` (default false).

  The maintainer runs it on staging and live. No existing data changes: every current listing starts outside any festival, and no account has Runs Festivals until staff tick it, staff accounts included.
- **Code, Backstage:**
  - New: a `Festival` model with its factory, policy, form request and Backstage controller, plus the pages `dashboard/festivals/Index`, `Create` and `Edit`.
  - Changed: the listing form and `ListingRequest`, the sidebar (`AppSidebar.vue`) and the shared `auth` props in `HandleInertiaRequests`.
- **Code, public:**
  - New: a public controller for the festival page.
  - Changed: `Listings.vue` and `ListingsIndex.vue`, and the covers in `ListItem.vue`, `ListingRow.vue` and `ListingsVenueIndex.vue`.
  - Changed: `SiteMeta`, `ListingMeta` and `Sitemap`.
- **Code, widgets:** `Widget`, `WidgetFeed`, `WidgetCache`, `WidgetRequest` and the Backstage `WidgetController` learn a third kind of owner.
- **Code, Office:** a new `app/Nova/Festival.php`, plus changes to `app/Nova/Listing.php` and `app/Nova/Promoter.php`. `app/Nova/User.php` gains the Runs Festivals tickbox, and a new `app/Nova/FestivalRunner.php` lists the accounts that have it, as `app/Nova/Publisher.php` lists publishers.
- **Code, the role:** `User::ROLES` gains `runs_festivals`, which carries it into `auth.roles` for Backstage. Tests that list every role (`tests/Feature/OfficeUserListsTest.php`, `tests/Feature/UserRoleTest.php`) gain the new column. `UserRoleTest`'s staff test changes too, because staff no longer hold every role without ticking it.
- **Shared code:** the logo and banner handling in the Backstage `PromoterController`, and the rules in `PromoterRequest`, become shared with festivals rather than copied.
- **Unchanged:**
  - the Backstage home panels;
  - the social share image, which stays the plain cover;
  - promoter and venue pages, apart from the promoter page's festival list.
