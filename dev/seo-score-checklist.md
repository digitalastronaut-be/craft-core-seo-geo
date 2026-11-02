# SEO Score

A per-entry (or any element with an `SeoField`) checklist that scores how well the page is set
up for SEO, computed on save and surfaced in the CP next to the SEO field.

## Context (why)

`SeoField`/`SeoData` (`src/fields/SeoField.php`, `src/models/SeoData.php`) validate structural
input (canonical URL is a valid URL when set) but deliberately **allow a blank meta
title/description** — that used to be a hard validation error, but an empty title/description is
a valid authoring state (e.g. not ready yet), not something that should block saving. What's
missing is a score that covers that case plus everything that can only go wrong in the *rendered
output* — a missing `<main>`, a broken link, a skipped heading level — rolled into one
number/status an editor can see at a glance without reading template code.

## Checklist

Every check below runs in the same queue job (see [Implementation Plan](#implementation-plan)
step 4) — none of them run inline during the save request, so save speed is unaffected by this
feature entirely, and there's exactly one code path to reason about instead of a sync/async split.

| # | Check | How it's checked |
|---|-------|-------------------|
| 1 | Valid title | `SeoData::$metaTitle` is non-null/non-blank. **Warning** (not fail) if resolved length is outside ~10–60 chars — outside that range it's likely to get truncated or padded in the SERP, but a short/long title isn't *wrong*. |
| 2 | Valid description | `SeoData::$metaDescription` is non-null/non-blank. **Warning** if resolved length is outside ~50–160 chars, same rationale as title. |
| 3 | SEO image provided | `SeoData::getImage()->exists()`. **Warning** if the asset exists but has no `alt` text set, or is smaller than the common OG minimum (1200×630). |
| 4 | Canonical URL is valid | Already validated in `SeoField::validateSeoData()` (skipped if unset — a canonical override is optional). Score check reuses that same `UrlValidator` result rather than re-implementing it. **Warning**, not fail, if it's set but doesn't match the element's own URL — that's sometimes intentional (e.g. a syndicated/duplicate page), so it's surfaced rather than penalized hard. |
| 5 | Not accidentally de-indexed | ✅ done (`IndexableCheck`). **Not applicable** (not scored either way) if `noindex` is set in `SeoData::$robots` — that's often intentional, not wrong, so there's no failure state, only "applies and passes" or "doesn't apply." Once checks 7-12 exist, this is also where their short-circuiting would hook in (no point scoring a page that's explicitly told not to be indexed), but that's not wired up yet since there's nothing to short-circuit. |
| 6 | No duplicate title/description | ✅ done (`DuplicateContentCheck`). Fails if either the title or description exactly matches another element's on the same site, read from the denormalized `metaTitle`/`metaDescription` columns on `coreseogeo_seoscores` (added via migration, kept in sync by `SeoScoreService::process()` on every run) rather than scanning the field's own JSON value. **Not applicable** if neither a title nor a description is set yet. |
| 7 | Page has a `<main>` element | ✅ done (`HasMainCheck`). Fetches the element's rendered HTML (its own front-end URL) and parses it with `Masterminds\HTML5` (already vendored via `craftcms/cms`) — see `SeoScoreCheckContext::getDocument()`. Fails on zero `<main>` elements, **warning** (not fail) on more than one. **Not applicable** if the page couldn't be fetched/parsed at all (no front-end URL, request failure) — that's a different problem than the template missing a `<main>`. |
| 8 | `<main>` has at least one link | ✅ done (`MainHasLinkCheck`). `DOMXPath` query against the same parsed document, count ≥ 1. **Not applicable** if there's no document, or no `<main>` (check 7's problem to report, not this one's). |
| 9 | No broken links | **Not yet built** — the hard one. Collect every `//a[@href]` on the page (not just inside `<main>` — a broken footer/nav link is still broken), resolve relative URLs against the page's own URL, dedupe, skip `mailto:`/`tel:`/`#`-only anchors, request each remaining unique URL (HEAD, falling back to GET if HEAD isn't allowed) through a shared, TTL-cached result store so links shared across many pages (nav, footer) aren't re-requested per element. 4xx/5xx = broken; timeouts/connection errors = broken; 403/429 = inconclusive, skipped rather than failed (those usually mean "bot-blocked," not "actually broken"). Deferred: needs the cache-layer/scope decision below settled first, and is a materially bigger unit of work than 7/8/10–12 (which only ever look at the one already-fetched page). |
| 10 | Heading levels are structured correctly | ✅ done (`HeadingStructureCheck`). Walks `<h1>`-`<h6>` in document order: fails if more than one `<h1>` exists, fails if any step increases by more than 1 (e.g. `h2` → `h4` with no `h3` between). Decreasing levels (closing a section) is always fine. **Not applicable** if there's no document, or no headings at all. |
| 11 | Content images have alt text | ✅ done (`ImageAltTextCheck`). `//main//img` with no `alt` attribute, or an empty one, fails this check. Scoped to `<main>` deliberately — decorative images in shared chrome (header/footer) are a template concern, not this entry's content. **Not applicable** if there's no document, or no images inside `<main>`. |
| 12 | Open Graph / Twitter tags render non-empty | ✅ done (`SocialMetaTagsCheck`). Confirms `og:title`, `og:description`, `og:image`, and `twitter:card` resolve to non-empty values in the rendered `<head>` — catches a template fallback chain that silently resolves to nothing even though checks 1–3 passed. **Not applicable** if there's no document. |
| 13 | `<main>` has at least 300 words | ✅ done (`MainWordCountCheck`). Concatenates the `textContent` of every `<main>` node, splits on whitespace, fails below 300 words — thin content is a common, otherwise invisible reason a page underperforms even when every other check passes. **Not applicable** if there's no document, or no `<main>` (check 7's problem to report, not this one's). The 300-word floor is a hardcoded constant (`MainWordCountCheck::MIN_WORDS`), not yet a setting — same "not yet configurable" state as the length/dimension thresholds in checks 1–3. |

> **Masterminds\HTML5 namespace gotcha:** it parses every element into the
> `http://www.w3.org/1999/xhtml` namespace even though the source markup has no `xmlns`, so a
> plain unprefixed XPath query like `//main` silently matches *nothing* against the returned
> `DOMDocument`. Every check above queries by `local-name()` instead (e.g.
> `//*[local-name()="main"]`) — do the same in any future check that walks this document, see
> `SeoScoreCheckContext::getDocument()`'s docblock.

### Pending state

A save doesn't produce a score by itself — it only triggers the job that will. Immediately after
save, the stored row is flipped to `pending = true` (a plain boolean column) with the *previous*
score/checks left in place (not cleared), so the CP shows "recalculating…" against last-known-good
data instead of flashing to zero. When the queue job finishes, it writes the new `score`/`checks`
and sets `pending = false` in the same update. See [Implementation Plan](#implementation-plan)
steps 4–5.

### Scoring

- Each check contributes a **weight** toward the total (title/description/image weighted
  highest — they're what actually shows in a SERP snippet — structural checks weighted lower).
  Weights live in `Settings` so they're tunable per install, not hardcoded.
- Each check resolves to **pass / warning / fail**. There's no per-check "not yet run" state —
  `pending` (above) is a single flag for the whole row, not tracked per check.
- Score = `(sum of weights for passing checks + half-weight for warnings) / total applicable
  weight`, as a 0–100 percentage. "Applicable" excludes checks 7–12 entirely when check 5 found
  `noindex` (see above).
- Suggested status bands: **Good** ≥ 90, **Needs work** 50–89, **Poor** < 50 — configurable, not
  load-bearing on anything else.

### Open decisions

- **Scope of check 9 (broken links).** Page-wide vs. `<main>`-only, as written above. Page-wide
  catches more real breakage but means a broken link in a shared layout fails *every* entry that
  uses it — which is arguably correct (it is broken everywhere) but will make a single dead link
  tank a lot of scores at once. Worth deciding before building the cache layer, since it changes
  what's "shared" in the cache.
- **Where the HTTP request comes from.** ✅ resolved — `Craft::createGuzzleClient()->get($element
  ->getUrl())`, the same approach `MarkdownService::_generate()` already uses for the llms.txt
  feature (see `SeoScoreCheckContext::getDocument()`). No Host-header/base-URL juggling needed --
  `getUrl()` already returns the fully-qualified absolute URL. Confirmed working from inside an
  actual queue worker in DDEV (not just a web request): verified via `craft resave/entries` +
  `craft queue/run` against this project's own entries. Elements with no front-end URL (headless/
  disabled) are skipped (`getDocument()` returns `null`). No cache-bypass header was needed for
  this project's setup; revisit if a Blitz-fronted install's checks end up scoring stale content.
- **Draft/revision saves.** Should a draft queue its own recalculation, or only canonical
  elements? Recommendation: skip entirely for drafts/revisions — queuing a job (and firing a
  request per link) on every autosave while someone is actively typing is wasteful; recalculate
  once, when the draft is applied.

## Implementation Plan

1. **Storage.** New table (migration in `src/migrations/`, constant added to `src/db/Table.php`,
   e.g. `SEOSCORE = '{{%coreseogeo_seoscores}}'`), one row per element+site+field (mirrors the
   `ownerId`/`fieldId` pattern already used by `StructuredData`). Start minimal — `id`,
   `elementId`, `siteId`, `fieldId`, `score` (smallint), `pending` (boolean, default `false`),
   plus the standard `dateCreated`/`dateUpdated`/`uid` — with a unique index on
   `(elementId, siteId, fieldId)` so writes are a plain upsert. Add later, as plain `ADD COLUMN`
   migrations with no backfill required: `checks` (JSON, per-check status + reason, once the CP
   detail view is built) and `metaTitle`/`metaDescription` (denormalized, indexed — only needed
   once check 6 is built). None of this requires touching existing rows; old rows just read as
   `null`/absent until the next recalculation. Bump `CoreSeoGeo::$schemaVersion`.
   `SeoScoreService::getScore()` should return a small model wrapping the row (not the raw AR)
   so call sites are insulated from columns arriving later.

2. **Check abstraction.** ✅ done — `SeoScoreCheckInterface` (`getKey()`, `getLabel()`,
   `getWeight()`, `validate(SeoScoreCheckContext $context): SeoScoreCheckResult`) plus one class
   per applicable row in the checklist table above, under `src/seochecks/`. The built-in set
   registers via `SeoScoreService::EVENT_REGISTER_SEO_SCORE_CHECKS` (a `RegisterComponentTypesEvent`,
   wired up in `PluginTrait::registerSeoScoreChecks()`) — same shape as the existing
   `Fields::EVENT_REGISTER_FIELD_TYPES` / `Elements::EVENT_REGISTER_ELEMENT_TYPES` registrations,
   so a future check (or a project-specific override) can be added without touching plugin code.
   Shipped so far: `ValidTitleCheck`, `ValidDescriptionCheck`, `SeoImageCheck`, `CanonicalUrlCheck`,
   `IndexableCheck`, `DuplicateContentCheck` (checks 1-6), plus `HasMainCheck`, `MainHasLinkCheck`,
   `MainWordCountCheck`, `HeadingStructureCheck`, `ImageAltTextCheck`, `SocialMetaTagsCheck`
   (checks 7, 8, 10-13 -- every check that needs the rendered page but not a multi-URL crawl).
   Check 9 (broken links) is the one still not built -- it's a materially bigger unit of work (a
   shared, TTL-cached per-URL result store across elements) than the others, which only ever look
   at the one already-fetched page, and its scope-of-crawl open decision above is still unsettled.

3. **`SeoScoreService`** ✅ done (registered in `ServicesTrait`, same as `StructuredDataService`):
   - `markPending(ElementInterface $element, int $fieldId): void` — upserts the row with
     `pending = true`, leaving any existing `score` untouched.
   - `calculate(ElementInterface $element, int $fieldId): void` — pushes a `CalculateSeoScore`
     job. This is the method's *only* job — it doesn't call `markPending()` itself, so a caller
     that wants the CP to show "recalculating..." in the meantime calls both explicitly.
   - `process(ElementInterface $element, SeoData $seoData, int $fieldId): SeoScoreModel` — runs
     every registered, applicable check, writes `score`, and sets `pending = false`. Called from
     `CalculateSeoScore::execute()`, not meant to be called directly from request-time code.
   - `getScore(ElementInterface $element, int $fieldId): ?SeoScoreModel` — reads the stored row
     for CP display.

4. **Hook into save.** ✅ done — `PluginTrait::registerSeoScoreCalculation()`, listening on
   `Elements::EVENT_AFTER_SAVE_ELEMENT`, registered unconditionally in `registerSharedEvents()`
   (not gated to CP requests) so console saves -- `resave/entries`, imports, etc. -- recalculate
   too. Guard clauses: skips drafts/revisions per the open-decisions note above; walks
   `$element->getFieldLayout()->getCustomFields()` and calls `markPending()` then `calculate()`
   for every `SeoField` found (handles more than one on the same layout). Verified against
   `craft resave/entries`: every entry carrying the field got a `pending` row immediately, and
   running the queue resolved them all to real scores.

5. **`CalculateSeoScore extends craft\queue\BaseJob`** ✅ done (holds `elementId`/`siteId`/
   `fieldId` only, re-fetching the element and field value itself rather than taking them as
   constructor objects, since job properties are serialized onto the queue; calls
   `SeoScoreService::process()`). ✅ **extended for checks 7, 8, 10-12** -- rather than the job
   itself fetching/parsing, that landed on `SeoScoreCheckContext::getDocument()` instead: it
   resolves the element's front-end URL (returns `null`, no-op, if there isn't one), makes the
   internal request via `Craft::createGuzzleClient()`, and parses the response with
   `Masterminds\HTML5` into a `DOMDocument` -- fetched at most once per run and shared by every
   check in that run that calls it, memoized on the context rather than eagerly done by the job,
   so the request only happens at all when at least one registered+enabled check actually needs
   it. Still to extend, for check 9 specifically: the broken-link check's own TTL-cached per-URL
   lookups (cache table or Craft's `Craft::$app->getCache()` component, keyed by a hash of the
   target URL) -- a different shape of caching than the single-document memoization above, since
   it needs to dedupe *across* elements, not just within one run.

6. **CP display.**
   - ✅ **Element index column** — `SeoField implements PreviewableFieldInterface`, overriding
     `getPreviewHtml()` to show the plain percentage (`Not calculated` / `Calculating...` / `67%`,
     no color yet). This is also what makes the field selectable as a table attribute at all —
     Craft only offers `PreviewableFieldInterface` fields in that picker.
   - ✅ **Edit-screen sidebar panel** — rather than a field-layout UI element (which would only
     show up wherever the field itself is placed in a tab), this went on
     `Element::EVENT_DEFINE_SIDEBAR_HTML` (`PluginTrait::registerSeoScoreSidebar()`), so it works
     for *any* element type the field ends up on, not just entries. Renders one
     `Cp::metadataHtml()` readout per `SeoField` found on the layout: a `Score` row, then every
     stored check's label mapped to a plain-text `Pass`/`Fail`/`N/A` + its message (added the
     `checks` JSON column, migration `m261017_150000_...`, specifically for this — populated by
     `SeoScoreService::process()` alongside the score, one entry per registered check regardless
     of applicability, so "N/A" rows render instead of being silently omitted). No color/styling,
     per the same "later" decision as the index column.
   - Still open: a sortable element-index **column** sort (today the column renders a percentage
     string, but nothing makes `ORDER BY` understand it numerically) -- fine for now, revisit once
     this needs to be sortable.

7. **Console command.** `SeoScoreController` (`php craft core-seo-geo/seo-score/recalculate-all`),
   built on the already-vendored `craft\queue\BaseBatchedElementJob` for the bulk case — needed
   after install, after changing check weights, or after a site-wide template change that could
   affect every entry's result at once.

8. **Settings.** Extend `models/Settings.php` (+ its CP settings template) with per-check
   enable/disable, weights, and the length/dimension thresholds from checks 1–3 — avoids
   hardcoding values that are genuinely going to differ per site/client.

9. **Verification.** Manually exercise every check's pass *and* fail case against real entries
   (including the `noindex` short-circuit and the draft-save guard from step 4); confirm the
   loopback request in step 5 actually resolves in DDEV before relying on it; confirm a rapid
   double-save doesn't leave two stale queue jobs racing to write the same row (the last one to
   finish should simply win — `process()` is a plain overwrite, not a merge).
