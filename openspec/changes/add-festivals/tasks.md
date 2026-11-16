# Tasks

## 1. Festivals and the Runs Festivals role in the data

- [x] 1.1 Create the migration with `php artisan make:migration --no-interaction`. It should:
  - create `festivals`, with `promoter_id` (`foreignId()->constrained()->cascadeOnDelete()`) and the same columns as `promoters`, with `about` as text;
  - add `listings.festival_id` (`foreignId()->nullable()->constrained()->nullOnDelete()`) and `listings.show_festival_mark` (boolean, default true);
  - add `users.runs_festivals` (boolean, default false, after `is_publisher`);
  - have a `down()` that drops the columns and then the table.

  **Do not run `php artisan migrate`:** the local `.env` points at staging's database. Verify instead that the test suite's in-memory database migrates, by running 1.2's tests.
- [x] 1.2 Add `app/Models/Festival.php` and `database/factories/FestivalFactory.php`, as in design.md:
  - **Festival:** `HasBrand`; belongs to `Promoter`; has many `Listing`s; morph-many `Widget`s.
  - **Its hooks:** `saved` forgets its widgets' cache; `deleting` deletes its widgets and its logo and banner files.
  - **`mark_background`:** an appended attribute, from `brand()->background`.
  - **Other models:** `Promoter::festivals()`, and a `deleting` hook on `Promoter` that deletes its festivals through the model; `User::festivals()` as a has-many-through; `Listing::festival()`, with `festival_id` and `show_festival_mark` made fillable and the flag cast to boolean.

  Add `tests/Feature/FestivalModelTest.php`, with `Storage::fake('do')`, covering:
  - an unset colour falls back to Good on Paper's, not the promoter's;
  - `mark_background` with and without a background colour;
  - deleting a festival leaves its listings with no festival, and deletes its widgets and files;
  - deleting a promoter deletes its festivals, their files and their widgets.

  Verify that `php artisan test tests/Feature/FestivalModelTest.php --compact` passes.
- [x] 1.3 Add the Runs Festivals role, following the publisher role as in design.md:
  - **`User`:** add `runs_festivals` to `User::ROLES` and to the class's `@property` list; add `User::ROLES_STAFF_TICK` holding it, and make `hasRole()` read those roles from the column even for staff; add `User::managedFestivals()`, which returns the festivals of the account's promoters when it holds the role and none when it does not.
  - **Office:** a "Runs Festivals" Boolean in `app/Nova/User.php`, with help text saying staff accounts need it ticked too, and `loginIsOptional()` taking it into account; `app/Nova/FestivalRunner.php` as a `RoleList` labelled Festivals, added to the Office's Users menu beside Publishers.
  - **Front end:** the role in `resources/js/types/auth.ts`.

  Add the new column to the role lists in `tests/Feature/OfficeUserListsTest.php` and `tests/Feature/UserRoleTest.php`. That extends their assertions without loosening any. Change `UserRoleTest`'s "being staff grants all of them" test to expect `runs_festivals` false for staff, because the spec now says staff do not hold it without ticking it, and keep every other role it checks. Add tests for the spec's "granting the role", "a new account", "a staff account", "a staff account that ticks it" and "listing who runs festivals" scenarios, and for `managedFestivals()` with and without the role. Verify that those files and `tests/Feature/DashboardTest.php`, which runs over every role, pass.

## 2. Shared promoter code

- [x] 2.1 Move the logo and banner swap out of `Dashboard\PromoterController::update()` into one method on `App\Services\Images\BrandImage`. It stores uploads, honours the remove flags, and deletes replaced files only once the row has saved. `PromoterController` calls that method.

  Move the rules `PromoterRequest` validates into a trait beside `ValidatesBrandColours`, and have `PromoterRequest` use it.

  Verify that `tests/Feature/DashboardBrandImageTest.php`, `tests/Feature/DashboardOwnershipTest.php` and `tests/Feature/PromoterAboutTest.php` pass unchanged.

## 3. Festivals in Backstage

- [x] 3.1 Add `app/Policies/FestivalPolicy.php`, mirroring `PromoterPolicy`:
  - staff are let through in `before()`;
  - viewAny, view, update and delete are allowed for a festival in `managedFestivals()`;
  - create is allowed for an account with Runs Festivals that runs any promoter;
  - replicate, restore and forceDelete are refused.

  Add `app/Http/Requests/Dashboard/FestivalRequest.php`, using 2.1's trait plus `promoter_id`, which is required on create, limited to the account's promoters, and ignored on update.

  Add `Dashboard\FestivalController` (index narrowed by `?promoter=`, create pre-selecting `?promoter=`, store, edit, update, destroy), using 2.1's `BrandImage` method, and register `Route::resource('festivals', ...)->except('show')` beside the promoters' route in `routes/web.php`.

  Add `tests/Feature/DashboardFestivalTest.php`, covering the spec's scenarios for:
  - creating a festival;
  - several promoters;
  - no promoter;
  - without Runs Festivals;
  - editing its own festival;
  - someone else's festival;
  - Runs Festivals taken away;
  - the promoter staying fixed after creation;
  - deleting a festival with listings;
  - its widgets going with it.

  Verify that the file passes.
- [x] 3.2 Add `resources/js/pages/dashboard/festivals/Index.vue`, `Create.vue` and `Edit.vue`:
  - **Fields:** follow `dashboard/promoters/Edit.vue`, with `BrandFields.vue` and `ImageField.vue`.
  - **Create:** a promoter select, shown only when the account runs more than one promoter.
  - **Edit:** the promoter shown read-only.
  - **Delete:** a button whose confirmation says the listings stay and the festival's widgets stop working on other sites.

  Lay it out phone-first, with nothing hidden or added on wider screens, and check it in light and dark. Verify that `npm run build` succeeds, and that a festival can be created, edited and deleted in the browser pane.
- [x] 3.3 Add `auth.festivals` (`id`, `name`, `promoter_id`) in `HandleInertiaRequests`, from `managedFestivals()`, widening `named()` to accept that query, and add it to `resources/js/types/auth.ts`.

  In `AppSidebar.vue`:
  - give each promoter's group a Festivals item, pointing at the festival index for that promoter, only when `auth.roles.runs_festivals` is set;
  - give `runs()` a `festival` key, so each festival gets a group with Event Listings and Gig Widgets.

  Make the Backstage listing index recognise `?festival=` (festivals the account runs only).

  Extend `tests/Feature/DashboardSidebarTest.php` (the prop, empty without the role), `tests/Feature/DashboardListingIndexTest.php` (the filter, including an id the account does not run being ignored, and being ignored without the role) and `tests/Browser/DashboardSidebarTest.php` (the spec's "a promoter with one festival", "no festivals" and "a promoter without the role" scenarios). Verify that all three pass.

- [x] 3.4 Let a new festival start from an earlier one, as in design.md:
  - **`BrandImage`:** a `copy()` that gives a stored picture a new file of its own, and a step that copies the earlier festival's logo and banner unless the form replaced or removed them.
  - **`FestivalRequest`:** a `from` field, accepted only for a festival in `managedFestivals()`, and kept out of `profile()`.
  - **`Dashboard\FestivalController`:** `create` reads `?from=` and passes the festivals the account runs, the starting festival's details with the name blank, and its promoter; `store` copies the pictures before saving.
  - **`festivals/Create.vue`:** a "Start from" select, shown only when there is a festival to start from, reloading the form with `?from=`; the form keyed by it, with a hidden `from` field.
  - **`FestivalPolicy::before()`:** not letting staff through for `replicate`.

  Extend `tests/Feature/DashboardFestivalTest.php` with the spec's "next year's festival", "pictures of its own", "changing a picture on the way" and "someone else's festival" scenarios, using `Storage::fake('do')`. Extend `tests/Feature/NovaFestivalTest.php` with "not copied in the Office", and `tests/Browser/DashboardFestivalPagesTest.php` with choosing a festival to start from filling the form. Verify that all three pass.

## 4. Listings in festivals

- [x] 4.1 Add the rule class `App\Rules\FestivalOfPromoter`, as in design.md.

  In `ListingRequest`:
  - add `festival_id` (nullable, using the rule) and `show_festival_mark` (boolean, coerced like the other checkboxes).

  In the Backstage `ListingController`:
  - make `promoters()` return, with each promoter, the festivals the account runs from `managedFestivals()`, plus the listing's kept festival;
  - make `create` pre-select `?promoter=` and `?festival=`;
  - leave `festival_id` and `show_festival_mark` out of what is saved for an account that does not have Runs Festivals.

  Extend `tests/Feature/DashboardListingTest.php` with the spec's scenarios:
  - choosing a festival;
  - mismatched festival;
  - a festival with no promoter;
  - venue owner edits a festival event;
  - without Runs Festivals (the festival and mark setting are kept, even when the request names other values).

  Extend `tests/Feature/DashboardListingDuplicateTest.php` with "building a programme". Verify that both files pass.
- [x] 4.2 In `resources/js/components/dashboard/ListingFormFields.vue`:
  - add the Festival select, filtered by the chosen promoter, cleared when the promoter changes and absent when there is nothing to choose;
  - add the "Show the festival mark on the cover" tickbox, ticked by default and shown only while a festival is chosen;
  - show neither unless `auth.roles.runs_festivals` is set.

  Make `ListingPreview.vue` show the mark (it uses 5.2's component).

  Extend `tests/Browser/DashboardListingFormTest.php` with:
  - choices following the promoter;
  - changing the promoter clearing the festival;
  - the tickbox appearing with a festival;
  - "adding a listing from the festival" pre-selecting both;
  - no Festival choice or tickbox for an account that does not have Runs Festivals.

  Verify that the file passes.
- [x] 4.3 Office changes:
  - Add `app/Nova/Festival.php`, with the promoter's fields and a `BelongsTo` Promoter that is required on create and read-only on update.
  - Give `app/Nova/Promoter.php` a `HasMany` of festivals.
  - In `app/Nova/Listing.php`, add a nullable Festival field that `dependsOn` Promoter, offers only that promoter's festivals and uses `FestivalOfPromoter`; and a "Show the festival mark on the cover" boolean.

  Add no colour beyond Nova's defaults. Extend `tests/Feature/NovaListingTest.php` with the spec's "in the Office" scenario, and add a test that staff can create a festival through Nova's resource endpoint. Verify that both pass, and check the Festival and Listing screens in Light, Dark and Goblin.

## 5. The public site

- [x] 5.1 Add one `Listing` scope that eager-loads venue, category, promoter and festival, with the festival limited to `id`, `name`, `logo`, `brand_background_colour` and `promoter_id`. Switch every listings action in `ListingController`, plus `listing()` and `preview()`, to it.

  Add a test to `tests/Feature/ListingsOrderAndFlashTest.php` asserting that a festival listing carries its festival and `show_festival_mark` on the all, period, venue, promoter, category and bookmarked pages and on its own page. Verify that it passes, and that `vendor/bin/sloppy diff main` reports no possible N+1 in `ListingController`.
- [x] 5.2 Add `resources/js/components/GOP/FestivalMark.vue`, as in design.md:
  - a bottom-right tile, a third of the cover's width, in `mark_background`, with the logo fitted inside;
  - shown only for a festival listing with the flag set and a logo;
  - layered under `SoldOutFlash`.

  Use it in `ListItem.vue`, `ListingRow.vue` and `ListingsVenueIndex.vue`, and not in `BookmarkedListingCompact.vue`. In `ListItem.vue`, add the "Part of …" line under the promoter, linking to the festival page. Add `festival` and `show_festival_mark` to the listing type in `resources/js/types/global.d.ts`.

  Add `tests/Browser/FestivalMarkTest.php`, covering the spec's scenarios:
  - listing page;
  - lists of listings;
  - sold out as well;
  - turned off;
  - no logo;
  - part of a festival.

  Verify that it passes.
- [x] 5.3 Move `ListingController::listingDays()` into a small shared helper, and call it from `ListingController`.

  Add the public `FestivalController` and register `listings/festival/{id}/{slug}`. Choose the listings and the `finished` flag as in design.md, and render `Listings` with `festival`, `finished` and `meta`.

  In `Listings.vue` and `ListingsIndex.vue`:
  - treat `festival` as a profile owner;
  - show the "has finished" line;
  - show the nothing-listed-yet state.

  Give `PromoterProfile.vue` an optional "by" link to the promoter.

  Add `tests/Feature/FestivalPageTest.php` (with `$this->travelTo()`), covering the spec's scenarios: header, wrong slug, halfway through, after the festival, nothing listed yet, and a missing festival being not found. Extend `tests/Feature/ListingsOrderTest.php` with the `listing-dates` "festival that has finished" scenario. Verify that both pass, along with `tests/Feature/PromoterPageTest.php`.
- [x] 5.4 Make `ListingController::promoter()` pass the promoter's festivals that have a current listing, and draw them as links in the promoter's header. Extend `tests/Feature/PromoterPageTest.php` with the "current and past festivals" scenario. Verify that it passes.
- [x] 5.5 Search engine data:
  - add `SiteMeta::festival()`;
  - give `ListingMeta` the festival as `superEvent`;
  - add festivals that have a listing to `Sitemap`.

  Extend `tests/Feature/PageMetaTest.php` with the spec's "festival page data" and "listing page data" scenarios, and `tests/Feature/SitemapTest.php` with a festival with listings being listed and one without being left out. Verify that both pass.

## 6. Festival gig widgets

- [x] 6.1 Add `Festival` as a widget owner, as in design.md:
  - **One owner-type map** used by `WidgetController::resolveOwner()`, `owners()` and `WidgetRequest`'s owner pattern.
  - **`Widget`:** `brand()`, `isForFestival()`, `spansVenues()`, `constrainListings()`.
  - **`WidgetCache::forgetListing()`:** the `festival_id` column.
  - **`WidgetFeed`:** `ownerPage()` returns the festival page; `events()` handles a finished festival.
  - **`WidgetController`:** owner labels, the edit page's back link, and `?festival=` on the index.

  Offer festivals as owners from `managedFestivals()` only.

  Extend `tests/Feature/DashboardWidgetTest.php` (making a widget for a festival the account runs; being refused for one it does not; no festival offered, and a festival owner refused, for an account that does not have Runs Festivals) and `tests/Feature/GigWidgetEmbedTest.php`, covering the spec's scenarios: festival widget, branding changed, and widget after the festival. Also cover the cache being dropped when a listing joins or leaves the festival. Verify that both files pass, with `tests/Feature/PublisherWidgetTest.php` unchanged and passing.

## 7. Finishing

- [x] 7.1 Rebuild the route cache with `php artisan route:cache` so Wayfinder sees the new routes. Then run `vendor/bin/pint --dirty --format agent` and `vendor/bin/sloppy diff main --fail-on=high`, followed by everything CI runs:
  - `composer test:lint`
  - `composer analyse`
  - `php artisan test tests/Feature tests/Unit --compact`
  - `npm run build`, then `php artisan test tests/Browser --compact --parallel`

  Verify that each passes, and report any failure with its output.

## Workflow follow-up

- The maintainer runs the new migration on staging and on live before pushing, because the code reads the new columns.
- The maintainer reviews, commits and pushes. A push to main deploys to staging and then live.
- Archive the change once it is live and checked.
