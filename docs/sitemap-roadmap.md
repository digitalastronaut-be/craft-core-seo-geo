# Sitemap Roadmap

Plan for building the plugin's `sitemap.xml` from nothing — the CP subnav entry and
an empty template exist today, but there's no `SitemapService`, no site-facing route,
and no settings surface.

## Context (why)

- The CP subnav item ("Sitemap", `CoreSeoGeo::getCpNavItem()`) and its route
  (`core-seo-geo/sitemap` → `src/templates/sitemap/_index.twig`, registered in
  `PluginTrait::registerCpUrlRules()`) already exist, but the template is just a
  title — nothing renders, there's no `SitemapService`, and there's no
  `sitemap.xml` site route at all.
- SEOmatic (installed in this project for comparison — see
  `llm-markdown-roadmap.md`'s note on `craft-llmify`) builds sitemaps from
  per-section/category/custom "sitemap groups" with individually configurable
  priority and change-frequency. It's powerful but most of that configuration
  surface sits unused on a typical site. "No fluff" means leaner: automatic by
  default, minimal settings, correct over configurable.
- The plugin already has the exact eligibility pattern this needs:
  `PluginTrait::_getSeoFields()`'s "any element with a `SeoField` on its layout"
  boundary (used by `SeoScoreService`, the automatic meta-tag injection, and the
  planned `llms.txt`), plus `SeoData::ROBOTS_NOINDEX` as the existing opt-out
  signal. The sitemap should reuse that signal, not invent a second one.
- Multi-site is free: a site URL rule resolves against whatever site the request
  already matched, same as `registerMarkdownUrlRules()`/`registerFaviconUrlRules()`.
  Each site gets its own `sitemap.xml` with no per-site branching required.

## Step 1 — Shape: a sitemap index + per-group sub-sitemaps, always ✅ done

Answers the "split per section?" question. `sitemap.xml` is always a
`<sitemapindex>` linking to `sitemap-<group>.xml` sub-files — one architecture
regardless of site size, not a size-based branch. Groups: one per
channel/structure section (keyed by handle), a single `singles` group
combining every single-type section, one `categories` group, and one
`custom-urls` bucket for Step 4's static custom routes.

**Singles get one combined group, not one each** (changed from the original
plan, which had every section including singles as its own group). A single
only ever has exactly one entry per site - the per-section split's whole
justification (cache granularity on sections that can hold thousands of
entries) buys nothing for something that can never hold more than one. One
file for every single-type section instead of a pile of one-URL files.

Why always, even for a small site: caching granularity (saving one entry in
`news` only needs to invalidate `sitemap-news.xml`, not the whole site's
sitemap — see Step 7), each group naturally stays well under Google's
50,000-URL/50MB-per-file sitemap protocol limit without anyone having to
think about it, and it gives Step 4's custom URLs an obvious home instead of
a special case bolted onto a flat file.

**Decide:**
- URL scheme for group files — `sitemap-<handle>.xml` vs. a prefixed
  `sitemap-sections-<handle>.xml`. Recommendation: plain `sitemap-<handle>.xml`,
  since handles are already unique across sections/category groups/the `pages`
  bucket.
- Whether every category group gets its own file or they collapse into one
  `sitemap-categories.xml`. Recommendation: collapse — category trees are
  usually small, and a file per group adds little.

## Step 2 — Element enumeration & eligibility

Answers "loop entries with URLs." `SitemapService` builds one `Generator` per
group, batch-iterating the relevant `ElementQuery` (`->status(Entry::STATUS_LIVE)
->siteId($siteId)->each()` — never `->all()`, so a site with 100k entries
doesn't load them all into memory at once) over:

- Every section/entry-type pair from `Craft::$app->getEntries()->getAllSections()`.
- Every category group from `Craft::$app->getCategories()->getAllGroups()`.

An element is included when:
- `$element->getUrl() !== null` — already excludes disabled, draft, and
  revision elements for free, since Craft's own `getUrl()` returns `null` for
  those.
- It does **not** have `SeoData::ROBOTS_NOINDEX` in its `SeoField`'s `robots`
  (via `_getSeoFields()`), when it has a `SeoField` at all.
- Its section/entry-type/category group isn't excluded via Step 3's setting.

`lastmod` is always `$element->dateUpdated` — no manual input, never stale.

An element with **no** `SeoField` on its layout is still included — the same
"don't vanish it" decision the `llms.txt` plan made. Absence of the field is
not an exclusion signal; only an explicit `noindex` is.

**Decide:**
- Whether Commerce products/variants get a dedicated enumeration branch now.
  Recommendation: defer — no evidence yet this project needs it, and it's
  additive later without touching anything above.

## Step 3 — Per-group include/exclude setting: decided against, not built

Reopened and closed for good. The two exclusion layers already in place —
`hasUrls` (no real URL at all, e.g. the Header/Footer singles) and
`ROBOTS_NOINDEX` on `SeoField` (has a URL but opted out) — already cover the
real need with zero configuration, and more precisely than a section-level
toggle ever could: noindex works per entry, so "most of this section belongs
in the sitemap except these three entries" is expressible today, where an
all-or-nothing section toggle can't express it at all. Adding one would
repeat the exact mistake already avoided with `priority`/`changefreq` — more
CP surface, and a worse failure mode than not having it (a toggle flipped off
and forgotten silently drops entries that are genuinely meant to be indexed,
with nothing to catch it). Not building this.

## Step 4 — Custom routes / `routes.php` entries ✅ partly done

Split resolved by actually reading Craft's own `Routes` service rather than
guessing: `Craft::$app->getRoutes()->getConfigFileRoutes()` (the raw
`config/routes.php` array) and `getProjectConfigRoutes()` (the CP's Settings
→ Routes, project-config-backed) are the two sources a "custom route" can
come from, and both feed the same pattern → template mapping Craft's router
resolves per-request.

- **Static patterns** (no dynamic placeholder, e.g. `contact-us`) are fully
  resolvable: the pattern *is* the URL, with no ambiguity at all.
  `SitemapService::getStaticCustomRouteUrls()` reads both sources, drops
  anything containing `<` (Yii's `<name:regex>` named-subpattern syntax - a
  literal `<` never legitimately appears in a URL path otherwise), and
  resolves the rest via `UrlHelper::siteUrl()`. These land in a new
  `custom-urls` sitemap group - deliberately not `pages`, since this
  project's own `pages` *section* handle proves that name is too likely to
  collide with a real section/category group handle.
- **Dynamic patterns** (e.g. `news/<slug:.+>`) genuinely can't be resolved
  automatically, full stop: Craft's router stores only the regex, never a
  registry of real values, because that logic belongs to whatever the
  destination template/controller does (an API call, a hardcoded list,
  another element query) - entirely outside anything the route table itself
  exposes. Guessing/brute-forcing real values would be both wrong and risky,
  so this isn't attempted.
- **The discovery aid is built**, not just decided: `getUnresolvedRoutePatterns()`
  returns exactly the dynamic patterns, surfaced as a warning on the new
  Sitemap settings page (`core-seo-geo/settings/sitemap`) via
  `CoreSeoGeoVariable::unresolvedSitemapRoutePatterns()`. Nothing vanishes
  from the sitemap silently - a developer sees precisely which patterns
  still need manual attention.

**Resolved — the event is built.** `SitemapService::EVENT_REGISTER_URLS`
(`RegisterSitemapUrlsEvent`) lets a module contribute URLs for exactly this
case: a custom route whose dynamic segment is resolved by that module's own
controller against data this plugin has no way to see. Shaped like
`RegisterUrlRulesEvent` itself — a plain mutable `$event->urls` array a
listener appends `{loc, lastmod?}` (or a bare URL string) to, not a
provider-class/interface — simpler, and there's nothing here that needs its
own object per entry. `getCustomUrls()` merges this with the static route
URLs into the same `custom-urls` group. Verified live: a temporary listener
in `modules/site/Site.php` correctly surfaced its URL in
`sitemap-custom-urls.xml`.

One real limitation that comes with it, worth naming rather than hiding:
this group's cache has no `TagDependency` to invalidate it automatically —
it isn't backed by an element query at all, so no element save can trigger
one. `SitemapService::clearCustomUrlsCache()` gives a module an explicit way
to say "my data changed, forget your cache" rather than just living with
`CACHE_DURATION` staleness.

**Still open:** whether the plain "Custom URLs" editable table (no PHP, for
one-off static cases) still earns its place now that the event exists. Left
out of this pass by request — a developer installing this plugin can already
cover that case with a one-line `routes.php` entry (handled automatically,
see above) or the event; the table's only remaining audience is a
non-developer site builder.

## Step 5 — Multi-site & hreflang cross-links

Each site's `sitemap.xml`/sub-sitemaps only ever contain that site's own URLs
— free, from the same per-site route resolution as everywhere else in this
plugin. Optionally decorate each `<url>` with `<xhtml:link rel="alternate"
hreflang="...">` tags by reusing `HreflangService::getAlternates()` (already
built for the `<head>` tags) — a real, Google-supported part of the sitemap
protocol, and nearly free since the data's already computed elsewhere.

**Decide:**
- Ship hreflang-in-sitemap in v1 or as a fast-follow. Recommendation: v1 — a
  few lines against an existing service, not new logic.

## Step 6 — Explicitly skip `priority` and `changefreq`

Answers the "do we expose priority/frequency" question: **no.** Google has
stated plainly, repeatedly, that Googlebot ignores both `<priority>` and
`<changefreq>`; Bing's own guidance says the same. They mattered in the
original 2005 sitemap-protocol era, before crawlers had their own recrawl
heuristics — not today. Exposing a per-group priority/frequency picker would
be real CP surface (another settings table, another thing an editor might
believe affects ranking) for no measurable effect on either crawler that
matters. Skipping it is exactly the "no fluff" call.

`<lastmod>` is the one time-related field worth emitting, and it's already
free from Step 2 — no setting, no editor input, always accurate.

**Decide:** nothing — this one's settled. Documented here so it doesn't get
re-opened later by someone trying to "complete" the sitemap spec without this
context.

## Step 6a — Pagination ✅ done

Any group over `SitemapService::PAGE_SIZE` (1,000) URLs is split into
`sitemap-<handle>-<page>.xml` files instead of one `sitemap-<handle>.xml` -
all listed flat in the same top-level index, never nested (the sitemap
protocol doesn't support an index pointing at another index, and real
crawlers, Google included, won't recurse through one anyway).

- **Why 1,000, not closer to the protocol's 50,000-URL ceiling:** this plugin
  ships to installs on infrastructure it doesn't control. `each()` already
  bounds memory, but not *time* - a single request still has to hydrate and
  render every URL in the file before it can respond, and Craft element
  hydration isn't free. A 100k-entry section rendered as one file is a
  believable timeout/crash on a modest host's first cold-cache crawler hit;
  1,000/page keeps every single request fast regardless of hosting quality.
- **Page boundaries are `elements.id`-ordered `LIMIT`/`OFFSET`, not keyset
  pagination.** Deliberately the simpler version: IDs are stable and
  monotonic, so a save between two page requests can't shuffle an
  already-served page's membership, even without the full cursor-based
  approach discussed for true per-page cache isolation (still a known,
  separate follow-up - see Step 7).
- **`resolveGroupKey()`** disambiguates a requested file key (e.g. `news-2`)
  into its base handle and page: an exact match against a known group handle
  always wins before trying to strip a trailing `-<digits>`, so a group
  handle that happens to itself end in a hyphen plus digits is never
  misread as a page suffix.
- **Caveat worth remembering:** the cache key is `{siteId}_{handle}_{page}}`,
  not keyed by `PAGE_SIZE` itself. Changing `PAGE_SIZE` in a future release
  (or a fork's config) without clearing the data cache would serve
  old-window content under the new page numbers until each key's `TagDependency`
  next invalidates or its cache naturally clears. Not a concern in normal
  operation (the constant doesn't change between requests), but worth a
  `clear-caches/all` note if this ever becomes configurable.

## Step 6b — `_isNoIndex()` no longer pays for `metaTitle`/`metaDescription` ✅ done

Found while estimating per-element cost for Step 6a: `_isNoIndex()` only
needs a `SeoField`'s `robots` array, but calling `getFieldValue()` on it
triggers the field's full `normalizeValue()` - which *also* resolves
`titleFormatRows`/`descriptionFormatRows` through Twig (`renderObjectTemplate()`)
to compute `metaTitle`/`metaDescription`, every single time, for every
element, purely as a side effect of reading one unrelated flag. Across a
large section that Twig-render cost is the dominant per-element overhead,
not the DB query.

Fixed by reading `robots` straight off the element's raw, not-yet-normalized
field data (`$element->getBehavior('customFields')->{handle}` - the same
dynamic property `getFieldValue()` itself reads, one step before it calls
`normalizeFieldValue()`), parsed through a new `SeoField::normalizeRobots()`
(promoted from a private method so both call sites share one definition).
Falls back to an already-normalized `SeoData`'s own `robots` for free if
something else already triggered normalization for this element this
request.

## Step 7 — Caching

Cache each sub-sitemap's rendered XML under a `TagDependency`, same approach
planned for `llms.txt` (`llm-markdown-roadmap.md`'s F3):
`collectCacheInfoForElement()` aggregated across every element a group's query
yielded, keyed by `[cacheTag, 'sitemap', $groupKey, $siteId]`. Any save that
would invalidate one page's own fragment cache also invalidates the
sub-sitemap containing it, automatically — no pending/queued flags, no job.

`Settings` saves (Step 3's exclusion list changing) need an explicit cache
clear, same caveat F3 already notes for its own non-element triggers. The
top-level `sitemap.xml` index is cheap enough it barely needs caching, but
clear it alongside the sub-sitemaps on a `Settings` save regardless, since its
contents (which groups exist) can also change there.

## Step 8 — Routes & controller

New `SitemapController` (site-facing, same shape as the existing
`FaviconController`/`MarkdownController`): `actionIndex()` → `sitemap.xml`,
`actionGroup(string $group)` → `sitemap-<group>.xml`. Registered via a new
`PluginTrait::registerSitemapUrlRules()`, called from `registerSiteEvents()`
alongside the existing markdown/favicon rules:

```php
$event->rules['sitemap.xml'] = 'core-seo-geo/sitemap/index';
$event->rules['sitemap-<group:[\w-]+>.xml'] = 'core-seo-geo/sitemap/group';
```

`Content-Type: application/xml; charset=UTF-8` on both actions.

## Step 8a — Environment gate + a real Settings → Sitemap page ✅ done

Two independent gates decide whether the sitemap exists on a given request
at all, both checked in `PluginTrait::registerSitemapUrlRules()` before even
registering the routes (not a runtime check in the controller — on a gated
request, the route simply doesn't exist, a plain 404 via Craft's normal
unmatched-route handling):

- **`general.disallowRobots`** — Craft's own, already-adopted "don't let
  search engines see this environment" signal (confirmed live on this
  project's own dev `.env`: `CRAFT_DISALLOW_ROBOTS=true`, which already adds
  `X-Robots-Tag: none` to every response, sitemap included). Deliberately
  not a separate "is this production" setting — a second, independently
  configured flag could drift out of sync with whatever already controls
  robots exclusion sitewide, and would require guessing at environment-
  naming conventions that vary per agency. Worth gating on this rather than
  trusting the header alone: a crawler can still fetch a public URL even
  when the response says not to index it — robots directives govern
  indexing, not access — so a public, enumerable sitemap is a minor content-
  disclosure problem on a staging site regardless of what header rides
  along with it.
- **`Settings::$enableSitemap`** (new, default `true`) — the site owner's
  own choice to use this feature at all, independent of environment.

`Settings::$enableSitemap` needed an actual settings page with a save
action — the first in this plugin; every other settings page is still a
static template placeholder with nothing to submit. Added a small
`SettingsController` (`actionSitemap()` renders the page, `actionSave()`
persists it) rather than Craft's generic `plugins/save-plugin-settings`
action posted to directly, specifically to avoid the split-settings footgun:
loading the *current* settings model first and only overwriting the posted
attributes, then saving `$settings->toArray()` (the full model, not just
what this page posted) is what keeps a save on this page from wiping out
whatever `enableHreflang`/`faviconAssetId` another page saved. The page
itself (`core-seo-geo/settings/sitemap.twig`) carries the `Settings::$enableSitemap`
lightswitch at the top and the Step 4 unresolved-route-patterns warning
below it — fixed in the same pass: that warning was written outside any
`{% block content %}` in a template that `{% extends %}` another, which Twig
silently drops entirely. It had never actually rendered.

## Step 9 — CP surface

Wire the existing empty `core-seo-geo/sitemap` CP page to actually show
something: each group's name, live URL count, a link to view its generated
file, and a "Clear cache" button. This page is a dashboard, not
configuration — the `enableSitemap` lightswitch lives on the Settings page
(Step 8a).

If this plugin doesn't already manage `robots.txt`, add a `Sitemap: {url}`
line there pointing at `sitemap.xml` once it exists; if `robots.txt` is out of
scope entirely, just surface the sitemap URL on this dashboard for manual
placement elsewhere.

## Open decisions (summary)

1. Group file URL scheme (Step 1) — recommended: plain `sitemap-<handle>.xml`.
2. Collapse all category groups into one file vs. one per group (Step 1) —
   recommended: collapse.
3. Defer Commerce enumeration until actually needed (Step 2) — recommended:
   yes, defer.
4. Per-group include/exclude setting (Step 3) — settled: not building it,
   `hasUrls` + per-entry `noindex` already cover the need.
5. Add a CP notice for unresolved custom routes (Step 4) — done, on the
   Sitemap settings page.
6. Ship hreflang-in-sitemap in v1 vs. fast-follow (Step 5) — recommended: v1.
7. `priority`/`changefreq` (Step 6) — settled: skip entirely, no re-litigating.

## Implementation plan

1. **Step 2 — `SitemapService` enumeration.** Core engine, no dependencies on
   anything else here.
2. **Step 3 — `Settings` additions** (exclusion list) + the new settings
   template.
3. **Step 1 — finalize the index/group file shape** against what Step 2's
   enumeration actually yields.
4. **Step 7 — caching.**
5. **Step 8 — controller + routes.**
6. **Step 9 — CP dashboard page** + `robots.txt` line if applicable.
7. **Step 4's remaining piece — custom-route extension points** (provider
   interface + event, and/or a Settings custom-URLs table) for the dynamic
   patterns the warning surfaces. The static-route half and the warning
   itself are already done. Left for a dedicated discussion before
   building.
8. **Step 5 — hreflang decoration.** Purely additive once Step 8's renderer
   exists.
9. **Verification** — confirm `noindex` actually drops a page from its group;
   confirm saving one entry only invalidates its own group's cache, not every
   group's; confirm each site resolves its own distinct, correct
   `sitemap.xml`; confirm a group with a very large element count still stays
   under Google's 50k-URL/50MB-per-file limit (or at minimum that nothing
   silently breaks past it); confirm both Step 4 extension points (provider
   event and the Settings table) actually surface their URLs in the `pages`
   group.
