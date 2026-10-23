# Markdown for LLMs/Agents (GEO)

Three related deliverables, all serving the same underlying need: letting AI
crawlers/agents consume this site's content as Markdown instead of scraping rendered
HTML.

- **Per-page `.md` variants** — appending `.md` to any entry's URL (or requesting the
  page itself with `Accept: text/markdown`) returns that page's content as Markdown.
- **Automatic serving to agents** — known AI bots and markdown-accepting requests get
  routed to the Markdown version transparently, without a redirect.
- **`/llms.txt`** — a generated, per-site curated index (the emerging, unofficial
  [llmstxt.org](https://llmstxt.org) convention) pointing agents at the `.md` pages
  above, the GEO equivalent of `sitemap.xml` but written for a reader, not a parser.

This supersedes the original `llms-txt-roadmap.md`, which only covered the third item.
Research into `samuelreichor/craft-llmify` (temporarily installed for reference,
`vendor/samuelreichor/craft-llmify`) changed the approach on several points below —
each one says why.

## Context (why)

- Most AI crawlers/agents (GPTBot, ClaudeBot, PerplexityBot, etc.) fetch raw HTML with
  no JS execution and no crawl budget to spare on exploring a whole site or parsing
  nav/footer/widget chrome out of a page. Markdown cuts straight to content.
- We already have everything the eligibility side of this needs without asking editors
  for new input: `SeoData::$metaDescription` (per-element) and the existing
  `ROBOTS_NOAI`/`ROBOTS_NOIMAGEAI` directives (`src/models/SeoData.php:38-39`) — an
  element already opted out of AI consumption via its SEO field should obviously be
  excluded from every one of these three deliverables. That exclusion rule falls out
  for free; nothing new to build for it.
- The plugin's `Sitemap` CP subnav entry (`core-seo-geo/sitemap`) and its template
  (`src/templates/sitemap/_index.twig`) exist today but render nothing — just a title.
  There's no `SitemapService`. None of this work depends on that existing first.
- Multi-site comes for free: Craft's own `UrlManager::EVENT_REGISTER_SITE_URL_RULES`
  resolves a registered rule against whichever site the request already matched, the
  same way every other site route in Craft does.

## Shared foundations

All three deliverables lean on the same four pieces of infrastructure. Build these
once, first.

### F1 — Content selection: an opt-in Twig tag, not whole-page conversion

**Changed from the original plan.** The original idea was to render an entry's normal
template and convert the whole `<body>` to Markdown, stripping nav/header/footer by
guessing at CSS selectors. `craft-llmify` does this better: an opt-in
`{% llmify %}...{% endllmify %}` tag (`src/twig/LlmifyNode.php`) that a template wraps
around the regions that *are* the content. The tag buffers its block's output, still
echoes it completely unchanged (zero effect on the real page), but also records that
HTML chunk against the current entry for later conversion. A companion
`{% excludellmify %}...{% endexcludellmify %}` tag carves sub-regions (an ad slot, a
related-posts widget) out of an otherwise-included block.

This is strictly better than guessing: the template author decides once what counts as
"content" for a given entry type, instead of this plugin maintaining a growing list of
selectors-to-strip per theme.

**Decide:**
- Tag names. Recommendation: `{% llmContent %}...{% endllmContent %}` and
  `{% excludeLlmContent %}...{% endexcludeLlmContent %}` — explicit about what they're
  for, and distinct from "geo" (which could mean geography) despite the plugin's name.
- New `src/twig/CoreSeoGeoExtension.php` (+ `LlmContentNode`/`TokenParser` pair),
  registered via `PluginTrait::registerTwigExtension()` (the plugin has no Twig
  extension yet — this is the first).

### F2 — HTML → Markdown conversion

`league/html-to-markdown` (not yet a dependency — add it), wrapped in a small
`MarkdownService::htmlToMarkdown(string $html): string` — the same service as F below
and Part A's generation/caching, not a separate class; conversion, generation, and
caching are all one feature with no reason to split across service boundaries. Match
`MarkdownService::htmlToMarkdown()` in craft-llmify for the cleanup pass worth copying:
strip leftover HTML tags inside converted link text, collapse empty list items left
behind after node removal, decode HTML entities, and collapse runs of blank lines to a
single blank line. These are all real conversion artifacts, not speculative polish.

### F3 — Caching: Craft's own `TagDependency`, not a custom pending/queued table

**Changed from the original plan.** The original Step 4 proposed a dedicated table with
`pending`/`queued` boolean flags, mirroring `SeoScoreService`'s per-element pattern.
`craft-llmify` does this with no custom table at all: it calls
`Craft::$app->getElements()->collectCacheInfoForElement($element)` — the exact
mechanism Craft's own template fragment caching (`{% cache %}`) uses — to build a
`TagDependency` for the rendered page, then caches the converted Markdown in
`Craft::$app->getCache()` keyed by `[cacheTag, 'page', $element->id, $element->siteId]`
under that dependency. The cache invalidates itself automatically whenever anything the
render actually depended on changes (the entry, a related entry, a global set, — whatever
Craft's own fragment cache would have invalidated on too). No pending flag to flip, no
debounce logic for bulk saves, no job to push from a save handler at all for the
per-page case.

This is simpler and more correct than the original plan and it's the one change to
adopt without reservation. Use it for both the per-page Markdown cache (Part A below) and
the `llms.txt` cache (Part C below).

**On generation failure** (e.g. a template has no `{% llmContent %}` blocks, or the
route isn't a template at all): cache the failure reason too
(`[cacheTag, 'page-failure', $element->id, $element->siteId]`, short TTL, same
dependency) so the CP sidebar panel (Step 6) can surface *why* a page has no Markdown
instead of just "none yet" — `craft-llmify`'s `getPageFailure()`/`rememberFailure()` is
worth copying directly, it's a small, genuinely useful pattern.

### F4 — Bot detection: a bundled JSON data file, not a hardcoded PHP list

New `BotDetectionService`, backed by `src/static/aibots/registry.php` or a plain
`src/static/aibots/bots.json` (same split as `SchemaOrgService`'s
`static/schemaorg/registry.php` — this plugin already has the convention of shipping a
compiled static data file for things that don't change at runtime). Match on
`stripos($userAgent, $bot) !== false`, same as `craft-llmify`'s
`BotDetectionService::getDetectedBotName()`. Seed it from their list
(`vendor/samuelreichor/craft-llmify/src/data/ai-bots.json`, ~180 entries, MIT-compatible
to reference the *names* of since these are just public user-agent strings, not code) or
pull an independently-maintained source.

**Decide:**
- Whether to also expose an admin-editable "additional bot user agents" setting (their
  pattern) so this list can be extended without a plugin release. Recommendation: yes,
  it's a single repeater field on the settings page and the list *will* go stale between
  releases.

## Part A — Per-page `.md` variants

### A1 — Route

New site URL rule, registered via `PluginTrait::registerLlmMarkdownUrlRules()` (called
from `registerSiteEvents()`, alongside the existing
`registerSiteTemplateRoot()`/`registerSeoMetaInjection()`):

```php
$event->rules['<slug:.*\.md>'] = 'core-seo-geo/markdown/render';
```

Matching `craft-llmify`'s regex exactly (`.*\.md`, not anchored to a prefix) means any
entry URI with `.md` appended resolves here directly, including the homepage
(`index.md` maps to `Element::HOMEPAGE_URI`, same special case they handle).

**Decide:**
- Optional URL prefix (e.g. serve under `/md/<uri>.md` instead of `<uri>.md`) to avoid
  ever colliding with a real `.md`-suffixed entry URI. Recommendation: no prefix by
  default (matches the llms.txt convention's expectation that the Markdown sits at the
  page's own URL plus `.md`), but make it an optional setting — `craft-llmify` has
  exactly this as `markdownUrlPrefix`, default empty.

### A2 — Controller

`MarkdownController::actionRender(string $slug)`: strip the `.md` suffix, resolve the
element via `Entry::find()->uri($uri)->siteId($currentSiteId)->one()`, render its
Markdown through F1–F3 above, and return it with:

- `Content-Type: text/markdown; charset=UTF-8`
- `X-Robots-Tag: noindex, nofollow` — **new, wasn't in the original plan.** The `.md`
  URL is a duplicate of the canonical page; it must never itself rank or get indexed.
- `Link: <canonical-html-url>; rel="canonical"` — **new.** Points back at the real page,
  the same courtesy the canonical HTML page should already extend to AI agents in
  reverse (A3).
- `Vary: Accept`

404 (not a 500) when the element doesn't exist, isn't servable (see eligibility, Part
C's Step 1), or rendering produced empty content.

### A3 — How the Markdown actually gets rendered: a decision, not a given

`craft-llmify` renders the entry's *real* Twig template in-process to capture the
`{% llmify %}` blocks, which means it has to save and restore a large pile of global
state afterward so none of it leaks into the real response: request path, user
identity, cookies, query params, and anything a plugin (SEOmatic, Formie) wrote to
response headers/cookies during that render. `MarkdownService::renderMarkdown()` +
`isolateRequest()` is roughly 150 lines doing exactly this, and even then a *failed*
attempt can't safely retry in-process within the same request — some plugins (Formie)
register assets as a side effect of rendering that can't be undone, so a failure forces
a 302 redirect to get a clean request instead of falling through.

They also support a simpler alternative they call "headless mode": instead of
rendering in-process, make an actual HTTP request to the page's own public URL and
convert *that* response's HTML. No state-isolation hacks at all, at the cost of one
internal HTTP round-trip per cache miss (irrelevant once F3's cache is warm) and
requiring the site to be reachable from itself.

**Decide:** which of these two to build for `core-seo-geo` v1.

- Recommendation: the HTTP-fetch approach. The in-process render path buys lower
  latency on a cold cache, but it's an entire category of state-leakage bugs
  (`core-seo-geo` touches response headers itself in `registerSeoMetaInjection()` — a
  bug here could leak another visitor's identity, cookies, or SEO meta into a cached
  page) in exchange for avoiding one internal HTTP call that only happens on a cache
  miss, under a cache that (per F3) invalidates itself correctly. "No fluff" favors the
  simpler, harder-to-get-wrong path here.

## Part B — Automatic serving to agents

Two independent signals decide whether a *normal* page request (no `.md` suffix) gets
served Markdown instead of HTML, matching `craft-llmify` exactly:

1. **Content negotiation** — `Accept` header contains `text/markdown`.
2. **Bot detection** — `User-Agent` matches F4's known-bot list.

### B1 — Routing mechanism

**New, not in the original plan at all** (the original plan had no auto-serve
story). `craft-llmify` hooks `Element::EVENT_SET_ROUTE` and rewrites the resolved
route to a different controller action when either signal above fires and the element
is eligible — true same-URL content negotiation at Craft's own routing layer, reusing
the exact Part A rendering path for both the `.md` URL and this negotiated case. Adopt
this directly; it's the correct place to intervene (before a template renders at all,
not after).

On a hit, set:
- `Content-Type: text/markdown; charset=UTF-8`
- `Vary: Accept, User-Agent`
- `Cache-Control: private, no-store`
- `Link: <md-url>; rel="alternate"; type="text/markdown"`

### B2 — Page-cache safety

**New, and easy to miss.** If this site ever sits behind a page cache (Blitz) or a CDN,
a cached HTML response could get poisoned with a bot's Markdown body — CDNs routinely
ignore `Vary`. Two things needed regardless of whether Blitz is installed today:

- `Cache-Control: private, no-store` on every negotiated response (above).
- If Blitz is present, hook `putyourlightson\blitz\services\CacheRequestService::EVENT_IS_CACHEABLE_REQUEST`
  (class-exists-guarded) and mark the request uncacheable whenever auto-serve is about
  to fire.

### B3 — Discovery tags on the normal HTML page

Extend the existing `registerSeoMetaInjection()` splice (`PluginTrait.php:304-324`, the
one that already injects `SeoField` tags before `</head>`) to also inject:

- `<link rel="alternate" type="text/markdown" href="{{ page }}.md">` — points this page
  at its own Markdown.
- `<link rel="describedby" href="/llms.txt">` — points every page at the sitewide index
  (Part C). **New** — the original plan had the reverse direction (`llms.txt` linking
  to pages) but nothing pointing a given page back at `llms.txt`.

## Part C — `/llms.txt`

Carried over from the original plan, with eligibility and generation strategy updated
to match the Shared Foundations above.

### C1 — Eligibility

The plugin's existing scope boundary is "any element that carries a `SeoField`" — the
same boundary `SeoScoreService` and the automatic meta-tag injection both use
(`PluginTrait::_getSeoFields()`). Use that here too rather than hardcoding
`craft\elements\Entry`: any element with a `SeoField` on its layout, a front-end URL
(`$element->getUrl() !== null`), not a draft/revision, not disabled.

**Exclude**, on top of that:
- `ROBOTS_NOINDEX` or `ROBOTS_NOAI` set in the element's `SeoData::$robots`.
- An element with no `SeoData` set at all is still eligible — fall back to the
  element's own title for link text and omit the description, rather than vanishing it
  from the one index an AI agent might ever see.

### C2 — Grouping and content

H2 per section (its name as heading), each with `[Title](url): description` bullets,
same as the original plan. Not building a pluggable
`EVENT_REGISTER_LLMS_TXT_GROUPS`-style extension point — no evidence yet of a real need
for custom grouping, and this plugin leans "no fluff."

**Decide (carried over, still open):**
- Section ordering: site's own section order vs. alphabetical. Recommendation:
  site's own order — it's already a deliberate editorial decision made elsewhere.
- Per-link description truncation rule (`metaDescription`, one line — exact character
  cap to pin down while writing the renderer).

### C3 — Title block and site-level copy

Add to `Settings`: `llmsTxtTitle` (nullable, falls back to `Craft::$app->getSystemName()`)
and `llmsTxtSummary` (nullable textarea — the blockquote line). Both per-site.

**Decide (carried over, still open):**
- Whether the optional free-text intro paragraph (between summary and first H2) is
  worth a third settings field in v1. Recommendation: leave it out until asked for.

### C4 — Generation and caching

**Changed from the original plan.** Drop the `coreseogeo_llmstxt` table and its
`pending`/`queued` flags entirely — use F3's `TagDependency` approach instead. The
`llms.txt` content is a pure function over every eligible element's title/description/
URL, so cache it under a `TagDependency` built from each eligible element's own cache
info (loop `collectCacheInfoForElement()` over the eligible set, same as F3, just
aggregated), keyed by `[cacheTag, 'llms-txt', $siteId]`. Any save that would have
invalidated an individual page's cache also invalidates this one, automatically, via
the same mechanism — section saves and `Settings` saves need an explicit
`Craft::$app->getCache()->delete()` (or their own dependency tag) since they aren't
element saves, but that's the only save-trigger plumbing this needs now, versus the
original plan's full pending/queued debounce design for bulk imports. A `TagDependency`
cache has no equivalent "regenerate in the background" step to get right — Craft
recomputes it lazily, once, on the next read after invalidation, same as any `{% cache %}` block.

### C5 — Controller and route

`FileController::actionLlmsTxt()` (or fold into `MarkdownController`), `text/markdown; charset=UTF-8`
(`craft-llmify` uses this content type for `llms.txt` too, not `text/plain` — match
it), registered via the same `registerLlmMarkdownUrlRules()` as Part A:

```php
$event->rules['llms.txt'] = 'core-seo-geo/markdown/llms-txt';
$event->rules['.well-known/llms.txt'] = 'core-seo-geo/markdown/llms-txt';
```

## CP surface and console commands

**Decide — where settings and a preview live.** `core-seo-geo/settings/global-geo`
already exists as a route (`PluginTrait::registerCpUrlRules()`) with an empty template
behind it — explicitly the "GEO" settings page. Recommendation: put all of this there —
`llmsTxtTitle`/`llmsTxtSummary`, the bot-detection toggle and additional-bots repeater,
the markdown URL prefix, plus a read-only `<pre>` preview of the current `llms.txt` and
a "Clear cache" button. Skip a new subnav entry for v1.

**Sidebar panel**, extending the existing `registerSeoScoreSidebar()` pattern
(`PluginTrait.php:165-179`): for any element with a `SeoField`, show whether its
Markdown is cached, its token estimate (see below), and the failure reason from F3 if
generation failed — mirrors the SEO score fieldset already shown there.

**Console commands**: `php craft core-seo-geo/markdown/clear-cache [siteId]` and
`php craft core-seo-geo/markdown/warm [siteId]` (fetch every eligible element's `.md`
URL once, to populate F3's cache ahead of the first real bot visit — relevant precisely
because F3 has no background-regeneration job of its own).

## Token estimate

**Correction to the original conversation, not something `craft-llmify` actually sets
as a header.** They compute `estimateTokens()` as `(int)ceil(mb_strlen($markdown) / 4)`
— the standard rough heuristic — but only to show an average on their CP dashboard, never
as an HTTP response header. Recommendation: use the same heuristic (no tokenizer
dependency needed for an estimate), surface it in the sidebar panel above, and *also*
add it as an `X-Content-Tokens-Estimate` response header on the Part A/B Markdown
responses if we want it — genuinely useful for an agent deciding whether to fetch, cheap
to compute from the string we already have, but understand this is this plugin's own
addition, not something borrowed from `craft-llmify`.

## Open decisions (summary)

1. Tag names for F1 (`{% llmContent %}` recommended).
2. Whether to expose an admin-editable additional-bots list (F4) — recommended yes.
3. Optional URL prefix for `.md` routes (A1) — recommended: none by default, but a
   setting.
4. In-process render (`craft-llmify`'s approach) vs. internal HTTP fetch (A3) — **the
   one decision most worth pinning down before writing any code**, since it shapes
   everything else in Part A. Recommendation: HTTP fetch, for simplicity.
5. Section ordering and description truncation rule for `llms.txt` (C2).
6. Whether to expose the optional free-text intro paragraph (C3) — recommended: no, for now.
7. Whether to add the `X-Content-Tokens-Estimate` response header, beyond the sidebar
   display (Token estimate section) — recommended: yes, it's cheap.

## Implementation plan

1. **F4 — `BotDetectionService`** + `src/static/aibots/bots.json`. No dependencies on
   anything else; build first so Part B can use it immediately.
2. **F2 — `MarkdownService::htmlToMarkdown()`**, wrapping `league/html-to-markdown` (new
   composer dependency) with the cleanup pass from F2.
3. **F1 — Twig extension and tags** (`{% llmContent %}`/`{% excludeLlmContent %}`),
   registered via `PluginTrait::registerTwigExtension()`.
4. **F3 — cache helper** (`TagDependency` via `collectCacheInfoForElement()`), plus the
   failure-cache pattern, on the same `MarkdownService`. No new table, no migration.
5. **Part A** — `MarkdownController::actionRender()`, the `<slug:.*\.md>` route, and the
   A3 decision (recommend: HTTP-fetch path) wired through F1–F3.
6. **Part B** — `Element::EVENT_SET_ROUTE` hook, B2's cache-safety headers (+ Blitz hook,
   guarded by `class_exists`), B3's discovery `<link>` tags on the existing meta
   injection.
7. **Part C** — eligibility query (C1) + renderer (C2) + `Settings` additions (C3) +
   cache wiring reusing F3 (C4) + route/controller (C5).
8. **CP surface** — `global-geo.twig` settings fields + preview + clear-cache button;
   sidebar panel extension; console commands.
9. **Verification** — confirm `noai`/`noindex` exclusion actually drops an element from
   both the `.md` route and `llms.txt`; confirm a save to one entry doesn't invalidate
   every other entry's cached Markdown (F3 should scope the `TagDependency` correctly);
   confirm the negotiated route never leaks headers/cookies between visitors (A3/B1);
   confirm a CDN/Blitz setup can't serve Markdown to a browser or HTML to a bot (B2);
   confirm multi-site resolves the right site's `llms.txt` and `.md` pages with no extra
   routing code.
