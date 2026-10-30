# Spec: Upgrade GoodOnPaper to Laravel 13

## Goal

Move the application from Laravel 12 (`laravel/framework` 12.70.2) to Laravel 13 without changing behaviour visible to users. When it is done, the full Pest suite (Feature, Unit and Browser) passes, Nova (`/office`) works, and the shop, adverts, gig widgets and auth flows behave as they do now.

## Current state

| Item | Now | Laravel 13 needs |
|---|---|---|
| PHP | 8.5.12 locally, `"php": "^8.4"` | Fine as is (L13 needs 8.3 or later) |
| `laravel/framework` | 12.70.2 (`^12.0`) | `^13.0` |
| `laravel/tinker` | 2.11.1 (supports `^12` only) | `^3.0` |
| `laravel/boost` | 2.10.3 | `^2.0` ✅ |
| `pestphp/pest` | 4.7.8 | `^4.0` ✅ |
| `laravel/nova` | `^5.7.5` (5.11.1 supports L13) | ✅ |
| Local Nova components | now allow `^13.0` | ✅ |

The repo is **not under git**. Do not start the upgrade until the backup in step 0 is done.

### Third-party compatibility (from installed `composer.json` files)

Already allow `^13.0`: inertia-laravel, cashier, fortify, breeze, wayfinder, dompdf, bugsnag-laravel, intervention/image-laravel, lettr-laravel, laravel-time-machine, ziggy (`>=9`), spatie/laravel-image-optimizer (nova-canvas dependency), spatie/eloquent-sortable (nova-sortable dependency).

These depend only on `laravel/nova: ^5.0`, so they will resolve once Nova does: outl1ne/nova-color-field, outl1ne/nova-sortable (+ nova-translations-loader), whitecube/nova-flexible-content, ultrasimplified/nova5-image-cropper. Each still needs a smoke test in Nova, because none of them declare Laravel 13 support directly.

## Blockers (resolved)

1. **Nova version.** ✅ Unpinned from `5.7.5` to `^5.7.5`. Nova 5.11.1 allows `illuminate/support ^13.0.0`. On its own, the bump to 5.11.1 removes `rap2hpoutre/fast-excel` and adds `laravel/sentinel` and `symfony/html-sanitizer`, so test Nova CSV export/import and the custom tools after updating.
2. **Local Nova components.** ✅ `nova-components/Gallery`, `VideoEmbed` and `Writers` now allow `illuminate/support ^13.0`. `ultrasimplified/nova5-image-cropper` only requires `laravel/nova ^5.0`, so it needs no change.

## Process

### 0. Prepare
- Take a full copy of the project directory and a database dump (`mysqldump`) of staging. Better still, initialise git first so the upgrade is a reviewable diff.
- Run the full suite on Laravel 12 and record the baseline: `php artisan test --compact`. Note any failures that already exist so they aren't blamed on the upgrade.
- Optional: run Boost's guided `/upgrade-laravel-v13` command (Boost ^2.0 is installed) as a cross-check against this spec.

### 1. Update dependencies
In `composer.json`:
```json
"laravel/framework": "^13.0",
"laravel/tinker": "^3.0",
"laravel/nova": "^5.7.5"   // already done
```
Then run:
```bash
composer update -W
php artisan nova:publish   # Nova assets after the version bump
php artisan vendor:publish --tag=laravel-assets --ansi --force
```
If Composer reports conflicts, fix each package's constraint. Don't use `--ignore-platform-reqs` or similar flags to force past them.

### 2. Changes the upgrade guide marks high impact

**Request forgery protection (CSRF).** `VerifyCsrfToken` is now `PreventRequestForgery` and also checks the `Sec-Fetch-Site` header.
- The codebase has no direct references to `VerifyCsrfToken` or `ValidateCsrfToken`, and no `withoutMiddleware` calls.
- `bootstrap/app.php` uses `$middleware->validateCsrfTokens(except: ['ad/*/impression'])`. That still works, but change it to `preventRequestForgery(...)` once it's confirmed in the L13 docs.
- **Test for real:** advert impression beacons (`ad/*/impression`), gig widget embeds on third-party sites (`routes/embed.php`, the `embed` group, `config('widgets.domain')`), Stripe/Cashier webhooks, and Nova forms. The origin check is new and may reject cross-site POSTs that used to pass.

### 3. Changes the upgrade guide marks medium impact

**Cache `serializable_classes`.** The L13 skeleton adds `'serializable_classes' => false` to `config/cache.php`.
- Cached values in this app are arrays (`AdvertRotation::live()`, which calls `->toArray()`) and strings (`WidgetCache`). No PHP objects are cached, so add the key set to `false`.
- After deploying, run `php artisan cache:clear`, since `CACHE_STORE=database` may hold serialized payloads from L12.

**`upsert` with MySQL.** `uniqueBy` must not be empty. The only call, `WidgetDailyStat::record()` (`app/Models/WidgetDailyStat.php:51`), passes `['widget_id', 'date']`, so no change is needed. `DashboardWidgetTest` and `GigWidgetEmbedTest` cover it.

### 4. Changes the upgrade guide marks low impact (checked against this codebase)

| Change | Status here |
|---|---|
| Cache, Redis and session cookie prefixes are now hyphenated | `config/cache.php`, `config/database.php` and `config/session.php` already use the hyphenated L13 defaults. No key or cookie changes. |
| Session `serialization` (skeleton sets `json`) | Not set in `config/session.php`. Session data is scalars and arrays (basket items, fulfilment value, order id, advert id), so it is safe to serialize as JSON. **Decision:** add `'serialization' => 'php'` for a seamless deploy, then switch to `json` in a later release. Switching now logs every user out. |
| Domain routes now take precedence | Embed routes register on `config('widgets.domain')` when it is set. They will now match before non-domain routes, which is the intended behaviour, but check the widget host and the main site with `widgets.domain` both set and unset. |
| `Js::from` no longer escapes unicode | Used in `resources/views/embed/layout.blade.php:31`. Check that widgets with accented names (e.g. venues) still render, and update any test assertions that expect `é`-style escapes. |
| `Str` factories reset between tests | No `Str::createUuidsUsing` etc. found. No action needed. |
| `JobAttempted` / `QueueBusy` property renames | Not used. |
| Manager `extend` binding, `Container::call` nullable defaults, polymorphic pivot names, `DELETE … JOIN … LIMIT`, Bootstrap pagination view names, `array_first`/`array_last` polyfill | None found in `app/`, `routes/`, `config/`, `bootstrap/` or `nova-components/*/src`. Re-check with the grep in step 6. |
| Password reset subject is now "Reset your password" | Check `tests/Feature/Auth` for assertions on the old subject. |

### 5. Sync the skeleton (optional, recommended)
Compare against https://github.com/laravel/laravel/compare/12.x...13.x and bring across config changes worth having. Leave the app's custom middleware, exception rendering and provider list in `bootstrap/` alone.

### 6. Verify
```bash
grep -rnE "VerifyCsrfToken|ValidateCsrfToken|exceptionOccurred|pagination::default|array_first|array_last" app routes bootstrap config tests nova-components/*/src
vendor/bin/pint --dirty --format agent
php artisan test --compact                 # full suite, compare with the baseline
npm run build                               # Wayfinder/Vite still generate cleanly
npm run build:nova-gallery && npm run build:nova-video-embed && npm run build-writers-prod
```
Then test by hand on `goodonpaper.test`:
- Nova at `/office`: create and edit a listing, the Gallery, VideoEmbed and Writers fields, image cropper, flexible content, sortable lists, colour field, nova-canvas.
- Shop: add to basket, checkout (Stripe test mode), confirmation page.
- Auth: log in, register, 2FA (Fortify), password reset email (check Herd mail).
- Adverts: rotation and impression POST.
- A gig widget embedded on an external test page.
- The 404/410 pages still render through Inertia.
- PDF downloads (dompdf), image uploads (Intervention, S3).

### 7. Deploy
- Make sure the production PHP version is ≥ 8.4 (the constraint in `composer.json`).
- `composer install --no-dev -o`, run migrations if any, then `deploy.sh` (it clears and rebuilds the route, config, view and app caches).
- Restart queue workers (`QUEUE_CONNECTION=database`) so they load the new framework code.
- Watch Bugsnag for 24–48 hours, especially for 419 errors (request forgery) and unserialize errors.

## Rollback
Restore the pre-upgrade copy of the project (or `git revert`) and `composer install`, then `php artisan cache:clear`. While session serialization stays `php`, no session or database rollback is needed.

## Done when
- [ ] `laravel/framework` is on 13.x and `composer.lock` is committed or backed up
- [ ] Full Pest suite matches or beats the L12 baseline
- [ ] Every item in the manual checklist in step 6 passes on staging
- [ ] Deployed to production with no new Bugsnag error types after 48 hours
