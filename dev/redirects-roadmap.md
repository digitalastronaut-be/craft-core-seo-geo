# Redirects Roadmap

Plan for building the plugin's last major v1 gap, already flagged in
`v1-polish-checklist.md`: `src/templates/redirects/_index.twig` is a 4-line stub, the
`redirects` subnav item (`CoreSeoGeo::getCpNavItem()`) already points at it, and there's
no `RedirectsService`, record, migration, or 404-catching route behind it. Three
outcomes, in dependency order (2 and 3 both need 1's data model; 3 also needs 2's
matching engine):

1. Capture every 404 hit, with a running count, so gaps are visible without guessing.
2. Let users register their own source → destination redirects.
3. Auto-create a redirect the moment an entry's URL changes, so nobody has to remember to.

## Context (why)

- `nystudio107/craft-retour` is installed in this project (`composer.json`) purely as a
  reference implementation to study, not as a dependency this plugin will wrap or
  require — it won't ship in `plugins/core-seo-geo/composer.json`. Retour's proven parts
  are reused deliberately (see Step 2's hook point); its data model is not — see Step 1.
- The plugin's existing "no fluff" bar (`sitemap-roadmap.md`'s priority/changefreq call,
  Settings' `enableX` toggles) applies here too: a lean, opinionated redirect engine, not
  a full-featured clone of Retour's regex-priority-fullurl-vs-pathonly configuration
  surface.
- Every other CP listing page in this plugin (`TrackingScriptsController` +
  `tracking-scripts.twig`/`tracking-script-edit.twig`) already establishes the UI/
  controller shape to reuse: a plain Twig table (no VueAdminTable), flash+redirect for
  the add/edit form, ajax `asSuccess()`/`asFailure()` for delete. Redirects reuses that
  shape rather than inventing a second one.
- Table names live in `db\Table` (`STRUCTUREDDATA`, `SEOSCORE`, `TRACKINGSCRIPTS`, …);
  two new constants join that list. Settings' `enableX` boolean pattern
  (`enableSitemap`, `enableHreflang`, …) extends the same way.

## Step 1 — Two tables, not Retour's three

Retour splits storage into `retour_redirects` (regex), `retour_static_redirects`
(exact-match), and `retour_stats` (every hit, matched or not, with a `handledByRetour`
flag to tell them apart). Three tables for what is really two concerns: *redirects
users configured* and *URLs that 404'd*.

**This plan uses two tables instead:**

- **`coreseogeo_redirects`** — one row per registered redirect, exact or regex alike,
  distinguished by a `matchType` column rather than which table it lives in:

  | column | type | notes |
  |---|---|---|
  | `id` | PK | |
  | `siteId` | `int` nullable | `null` = applies to every site |
  | `matchType` | `string(10)` | `'exact'` or `'regex'`, default `'exact'` |
  | `sourceUri` | `string(255)` | normalized path (no leading slash, no query string); a regex pattern when `matchType = 'regex'` |
  | `destinationUri` | `string(255)` nullable | relative path or absolute URL; `null` only when `statusCode = 410` |
  | `statusCode` | `smallint` | `301` (default), `302`, or `410` |
  | `enabled` | `boolean` | default `true` |
  | `sourceElementId` | `int` nullable, FK → `elements.id` `ON DELETE SET NULL` | set when Step 5 auto-created this row; `null` for a manually-added one |
  | `hitCount` | `int` | default `0` |
  | `dateLastHit` | `datetime` nullable | |
  | `dateCreated`, `dateUpdated`, `uid` | — | standard |

  Non-unique composite index on `(siteId, sourceUri)` for the exact-match lookup (Step
  3). Not a *unique* index: two regex rows can legitimately share a literal string
  before either is compiled, and enforcing uniqueness only for `matchType = 'exact'`
  needs a partial index MySQL doesn't support cleanly — so uniqueness for exact matches
  is instead a `RedirectsService::save()`-time application check, not a DB constraint.

- **`coreseogeo_404s`** — one row per **distinct unmatched** 404 URI, not one row per
  hit:

  | column | type | notes |
  |---|---|---|
  | `id` | PK | |
  | `siteId` | `int` notNull | |
  | `uri` | `string(255)` | normalized the same way as `sourceUri` above |
  | `referrer` | `string(2000)` nullable | last-seen `Referer` header, if any |
  | `hitCount` | `int` | default `1` |
  | `dateLastHit` | `datetime` | |
  | `dateCreated`, `dateUpdated`, `uid` | — | standard |

  Unique index on `(siteId, uri)` — this is what makes Step 2's upsert atomic.

**Why not Retour's `handledByRetour` flag on one shared table:** a row that's already
redirected no longer needs fixing, so it shouldn't linger forever in the "gaps to fix"
list requiring a filter to hide it. `coreseogeo_404s` only ever receives a row for a
URI that found **no** redirect match (see Step 2) — a direct, always-actionable list.
A redirect's own `hitCount`/`dateLastHit` already answers "how often is this redirect
used", which is the metric Retour's `stats` table exists to approximate indirectly.

**Decide:** none — this shape is settled, documented here so a future pass doesn't
re-propose Retour's three-table split as "more complete."

## Step 2 — Capturing every 404

**Hook point: `craft\web\ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION`.** This is the one
piece of Retour's architecture worth keeping as-is rather than reinventing: Craft's
router doesn't throw a 404 itself for "no template/element matched a site request" —
that only surfaces once Craft's own request handling has already tried and failed, deep
inside normal controller/template resolution. Catching it any earlier (e.g.
`UrlManager::EVENT_BEFORE_RESOLVE_REQUEST`) can't see that outcome yet; catching it here
— before Craft renders its own error page — is the only point that both knows a 404
truly occurred and can still intercept the response. Registered in
`PluginTrait::registerSiteEvents()` (already site-request-gated by `registerEvents()`,
so no extra guard needed), guarded inside the handler for `getIsConsoleRequest()` and
`getIsPreview()`/`getIsLivePreview()` the same way Retour's `handle404()` does — a Live
Preview iframe hitting a stale URL shouldn't log or redirect.

`RedirectsService::handleNotFound(NotFoundHttpException $exception)`:

1. Normalize the request path: `urldecode()`, strip the query string, trim the leading
   slash. Full-URL matching (Retour's `fullurl` option, including scheme+host) is **not
   supported** — path-only is the one mode this ships, matching every other URL-shaped
   thing in this plugin (sitemap entries, markdown routes). Decided against for the same
   reason Step 6 of `sitemap-roadmap.md` skipped per-group priority: real CP surface
   (a mode picker) for a case that doesn't come up on a single-domain-per-site install.
2. Skip entirely if the path matches a small hardcoded noise filter — bot-probe paths
   that would otherwise flood the log with nothing actionable: `wp-admin`, `wp-login.php`,
   `xmlrpc.php`, `.env`, `.git`, and anything ending in `.php`/`.asp`/`.aspx`/`.jsp` on an
   install that doesn't serve any of those. Hardcoded, not a `Settings` field — if this
   needs to grow, it's a code change, not new CP configuration (same call as Step 3 of
   the sitemap roadmap: a toggle nobody remembers to revisit is worse than no toggle).
3. Call `RedirectsService::findMatch($siteId, $path)` (Step 3). If it returns a match:
   issue the redirect (`statusCode`, `Location` header) and increment that redirect row's
   own `hitCount`/`dateLastHit` — **not** `coreseogeo_404s`, since this path is now
   handled, not a gap.
4. Otherwise, upsert `coreseogeo_404s`: `Craft::$app->db->createCommand()->upsert(
   Table::NOTFOUNDLOG, ['siteId' => ..., 'uri' => $path, 'referrer' => ..., 'hitCount' =>
   1, 'dateLastHit' => ...], ['hitCount' => new Expression('[[hitCount]] + 1'),
   'dateLastHit' => ..., 'referrer' => ...])`. **One atomic DB round-trip**, not
   Retour's read-then-write-in-PHP — under concurrent bot traffic hitting the same dead
   URL, a read/increment/save cycle can lose increments to a race; an `INSERT ... ON
   DUPLICATE KEY UPDATE` can't, because the increment happens inside the database, not in
   PHP between two round-trips. This is the one concrete "better than Retour" piece the
   prompt asked to look for, not just a different file layout.

**Decide:** nothing open here — hook point, normalization scope, and the noise filter
are all settled above.

## Step 3 — The redirect matching engine

`RedirectsService::findMatch(?int $siteId, string $path): ?MatchResult`:

1. **Exact match first**, via the `(siteId, sourceUri)` index: `WHERE matchType =
   'exact' AND enabled = 1 AND sourceUri = :path AND (siteId = :siteId OR siteId IS
   NULL) ORDER BY (siteId IS NULL) ASC LIMIT 1` — a site-specific row wins over an
   all-sites row when both exist for the same path, expressed as an `ORDER BY` rather
   than two separate queries.
2. **Regex fallback**, only if step 1 found nothing: load every enabled `matchType =
   'regex'` row for this site (+ all-sites rows), ordered by `id ASC`, and test each with
   `preg_match()` against `$path` until one hits. **Match order is creation order, full
   stop — no separate `priority` column.** Retour has one; this plan deliberately
   doesn't, for the same reason the sitemap roadmap dropped `priority`/`changefreq`: it's
   CP surface (a number to assign and remember) for a conflict that will come up on a
   tiny minority of installs with multiple overlapping regexes, and "whichever was
   created first wins" is a perfectly legible, zero-configuration default for that rare
   case.
3. A regex destination may reference capture groups (`$1`, `$2`, …); substitute via
   `preg_replace($sourceUri, $destinationUri, $path)` rather than a second
   `preg_match()` + manual interpolation pass.
4. Both branches are read through a **per-site cached array**, not a live query per
   404 — a 404 flood from bot traffic shouldn't mean a 404 flood of database queries.
   `Craft::$app->getCache()->getOrSet(['core-seo-geo', 'redirects', $siteId], fn () =>
   …)`, invalidated via a `TagDependency` (`['core-seo-geo', 'redirects']`, matching
   `SitemapService`'s own caching convention) cleared in `RedirectsService::save()`/
   `delete()`. Redirect tables stay small (hundreds, not millions of rows) even on a
   large site, so caching the full enabled set is cheap and avoids both a DB round-trip
   and a `preg_match()` loop on every single unmatched request.

**Decide:**
- Case sensitivity — recommendation: case-sensitive, exact string compare, matching how
  Craft stores `element.uri` itself (no silent lowercasing that could make two distinct
  real URIs collide).

## Step 4 — Manual CP management

New `RedirectsController` (CP-only, `requireAdmin(false)` like `TrackingScriptsController`):

- `actionIndex()` — the "Redirects" tab: lists `coreseogeo_redirects`, sorted by
  `hitCount` descending so the most-used redirects surface first. Columns: source,
  destination, status code badge, match type, hit count, enabled status
  (`Cp::statusLabelHtml()`, same teal/gray convention `TrackingScriptsController::
  _enabledStatusHtmlById()` already uses), edit/delete actions.
- `actionNotFoundLog()` — the "404s" tab: lists `coreseogeo_404s`, sorted by
  `hitCount` descending. Columns: URI, type, hit count, last hit (relative time),
  referrer, a "Create redirect" link to `redirects/new?source=<uri>` (Step 5's add form
  reads a `source` query param to pre-fill `sourceUri` — no extra controller action
  needed for that hand-off), and a delete action for manual cleanup of log entries
  nobody intends to fix. The "type" column (Asset/Page) isn't a stored column — it's
  `NotFoundLogEntry::getIsAsset()`, inferred from whether the URI's last path segment
  has a file extension. A page's `uri` in Craft is never extension-less and an asset's
  filename always is, so the URI shape alone is enough; no schema change, no volume/
  filesystem lookup needed just to label a row in a list.
- `actionEdit(?int $id, ?Redirect $redirect)` / `actionSave()` / `actionDelete()` — same
  three-action shape as `TrackingScriptsController`'s edit/save/delete trio, including
  the re-render-with-posted-data-on-failure pattern via `setRouteParams()`.
- `actionDeleteNotFoundLogEntry()` — ajax delete for a single `coreseogeo_404s` row.

Subnav: `redirects` already exists in `CoreSeoGeo::getCpNavItem()` pointing at
`core-seo-geo/redirects`; its own two tabs (Redirects / 404s) are template-level tabs
inside that one page, the same way Settings' sitemap page already combines a toggle and
a warning panel on one page rather than spawning more top-level subnav entries.

Add/edit form fields: Source URI, Match type (Exact/Regex select), Destination (hidden
entirely when Status code = 410 Gone — the one field whose visibility genuinely depends
on another), Status code (301/302/410 select), Site (select, shown only on a multi-site
install — `Craft::$app->getIsMultiSite()`), Enabled (lightswitch). Read-only metadata on
an existing row: hit count, last hit, and — when `sourceElementId` is set — "Auto-created
from: {entry title}" linking to that entry, so a site owner can tell at a glance which
redirects they added themselves versus which Step 5 generated.

## Step 5 — Automatic redirects on entry URL change

Hook `craft\services\Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI` /
`EVENT_AFTER_UPDATE_SLUG_AND_URI` — kept from Retour's approach because it's the correct
tool, not a case needing reinvention: these two events fire specifically and only when
Craft recalculates an element's URI (slug edit, structure move changing a parent-based
URI, a section's URI format changing in project config), decoupled from a plain content
save that leaves the URI untouched. Checking on every `EVENT_AFTER_SAVE_ELEMENT` instead
would mean comparing URIs on saves that never touched one.

- **Before:** stash `$element->uri` keyed by `"{$element->id}_{$element->siteId}"` in a
  local static array.
- **After:** compare the stashed value to the new `$element->uri`. Skip entirely when:
  the element is new, disabled, a draft/revision (`ElementHelper::isDraftOrRevision()`),
  propagating to another site, the old URI is empty/`null`, the old and new URIs are
  identical, or `Settings::$createUriChangeRedirects` is `false`.
- **Collision guard:** before creating anything, check whether the vacated old URI is
  now some *other* live element's current URI (slug reuse). If so, skip — redirecting a
  real, currently-live page to somewhere else would be a bug, not a feature.
- **Chain collapsing — the other deliberate improvement over Retour.** Before inserting
  a fresh `old → new` row, look for an *existing* auto-created redirect
  (`sourceElementId = $element->id`) whose `destinationUri` equals the URI just vacated.
  If one exists, **update that row's `destinationUri`** to the new URI instead of
  inserting a second row. Without this, an entry renamed twice (`/a` → `/b` → `/c`)
  leaves `/a → /b` and `/b → /c` as two separate rows: visiting `/a` now bounces through
  two redirect hops before reaching `/c`, a real, measurable SEO and latency cost
  (Google explicitly discourages redirect chains) that Retour's own implementation does
  not collapse. Collapsing it means `/a` always points straight at the entry's *current*
  URI, however many times it's moved.
- The new/updated row: `matchType = 'exact'`, `statusCode = Settings::
  $defaultRedirectStatusCode` (301 by default), `sourceElementId = $element->id`,
  `siteId = $element->siteId`.
- On element hard-delete: `sourceElementId` is `ON DELETE SET NULL` (Step 1), not
  cascade — the redirect itself (visitors still land somewhere sensible) stays useful
  even after the element that triggered it is gone; only the "auto-created from" CP
  attribution (Step 4) disappears.

**Decide:** none open — collision guard and chain collapsing are both part of this
step, not optional extras.

## Step 6 — Settings additions

Three new fields on `models\Settings`, following the existing `enableX` boolean
convention:

- `enableRedirects` (`bool`, default `true`) — master toggle: gates both the
  `ErrorHandler` listener (Step 2/3) and the URI-change listener (Step 5). Unlike
  `enableSitemap`, this is **not** also gated by `disallowRobots`/environment — redirect
  handling and 404 capture have nothing to do with search-engine visibility, and should
  keep working identically on staging and production.
- `createUriChangeRedirects` (`bool`, default `true`) — Step 5's own toggle,
  independent of the master switch, named to match Retour's own setting of the same
  purpose since it's a direct, recognizable concept for anyone who's used Retour before.
- `defaultRedirectStatusCode` (`int`, default `301`) — used as Step 5's auto-created
  status code and as the add-form's pre-selected value in Step 4.

## Step 7 — Migration & supporting classes

- `db\Table::REDIRECTS` = `{{%coreseogeo_redirects}}`, `db\Table::NOTFOUNDLOG` =
  `{{%coreseogeo_404s}}`.
- `migrations/m<date>_add_redirects_tables.php` — creates both tables per Step 1,
  indexes, and the `sourceElementId` → `elements.id` foreign key (`ON DELETE SET NULL`).
  Mirrors `m261022_130000_add_tracking_scripts_table.php`'s shape (idempotent
  `tableExists()` guard, `safeUp()`/`safeDown()`); `migrations/Install.php` gets the same
  two `createTable()` calls for fresh installs.
- `records\Redirect` / `records\NotFoundLogEntry` — `ActiveRecord`s, same shape as
  `records\TrackingScript` (constants for `matchType`/`statusCode` options, `rules()`
  validation, `init()` seeding defaults on a new record).
- `services\RedirectsService` — one service for both tables (not split into two, unlike
  the one-service-per-txt-file convention elsewhere in this plugin): the two concerns
  are tightly coupled by design — a 404 log row's entire purpose is to become a redirect
  — so splitting them would just mean constantly reaching across two services for one
  feature. Public surface: `handleNotFound()`, `findMatch()`, `getAllRedirects()`,
  `getRedirectById()`, `saveRedirect()`, `deleteRedirect()`, `getNotFoundLog()`,
  `deleteNotFoundLogEntry()`, plus the Step 5 element-event handlers. Registered in
  `ServicesTrait::config()`/`getRedirects()`, following every other service's exact
  pattern.

## Step 8 — Wiring

- `PluginTrait::registerSharedEvents()` gains `registerRedirectUriChangeHandling()`
  (Step 5's listeners — unconditional, since a CP slug edit or a console `resave/entries`
  both need to trigger it, not just site requests — same reasoning already documented
  on `registerContentSeoSync()`/`registerStructuredDataSync()`).
- `PluginTrait::registerSiteEvents()` gains `registerRedirectHandling()` (Step 2's
  `ErrorHandler` listener), both skipped when `!Settings::$enableRedirects`.
- `PluginTrait::registerCpUrlRules()` gains:
  ```php
  $event->rules['core-seo-geo/redirects'] = 'core-seo-geo/redirects/index';
  $event->rules['core-seo-geo/redirects/not-found-log'] = 'core-seo-geo/redirects/not-found-log';
  $event->rules['core-seo-geo/redirects/new'] = 'core-seo-geo/redirects/edit';
  $event->rules['core-seo-geo/redirects/<id:\d+>'] = 'core-seo-geo/redirects/edit';
  ```
  replacing the existing static-template rule
  (`$event->rules['core-seo-geo/redirects'] = ['template' => 'core-seo-geo/redirects/_index.twig'];`)
  with a real controller action.
- No new **site** URL rules — this feature intercepts already-unmatched requests via
  `ErrorHandler`, it doesn't add a route of its own, unlike `sitemap.xml`/`*.md`/etc.

## Open decisions (summary)

1. Case sensitivity of URI matching (Step 3) — recommendation: case-sensitive.
2. Everything else in this plan is a settled decision, not an open question — each step
   states its own reasoning so a later pass doesn't re-propose Retour's fuller
   configuration surface (full-URL matching, a `priority` column, three tables) as
   "completing" this feature.

## Implementation plan

1. **Step 1 & 7 — data model.** `db\Table` constants, the migration, both records. No
   dependents yet, so this can be built and verified (manually inserting/reading rows)
   in isolation before anything else exists.
2. **Step 6 — `Settings` additions.** Small, and Steps 2/3/5 all read these flags.
3. **Step 3 — `RedirectsService::findMatch()` + caching.** The core matching engine,
   testable on its own against rows inserted directly into `coreseogeo_redirects`.
4. **Step 2 — 404 capture + `ErrorHandler` hook.** Depends on Step 3 existing (a 404
   first tries to find a match before logging).
5. **Step 5 — URI-change auto-redirects.** Depends on `RedirectsService::saveRedirect()`
   (built as part of Step 1-era CRUD) and the collision/chain-collapsing logic.
6. **Step 4 — CP controller + templates.** Last, since it's the one part a human needs
   to look at and click through rather than something verifiable by reading code/DB rows.
7. **Step 8 — wiring.** Threaded through as each piece above lands, not a separate pass.
8. **Verification** — confirm a 404 with no match increments `coreseogeo_404s` exactly
   once per hit (including under concurrent requests, to validate the atomic upsert);
   confirm a matching redirect returns the configured status code and never touches the
   404 log; confirm a site-specific redirect wins over an all-sites one for the same
   path; confirm a regex redirect substitutes capture groups correctly; confirm renaming
   an entry twice in a row produces exactly one redirect row pointing straight at its
   final URL, not a two-hop chain; confirm the collision guard refuses to redirect a URI
   that's now a different live element's URL; confirm deleting an entry leaves its
   auto-created redirect intact with `sourceElementId` cleared; confirm the master
   `enableRedirects` toggle actually disables both the `ErrorHandler` listener and the
   URI-change listener, not just the CP page.
