# Codebase Review Findings

A full pass over the plugin as it stands (working tree includes the in-progress table-rename/
structured-data-mappings refactor) — bugs, gaps against the team's own roadmap docs, nice-to-haves,
and performance/security notes. Written 2026-11-02.

## 1. Bugs

- **Migration ordering breaks the upgrade path (critical, confirmed).** `db/Table.php` now points
  `STRUCTUREDDATAMAPPINGS`/`SEODATA` at their new snake_case names (`coreseogeo_structured_data_mappings`,
  `coreseogeo_seo_data`), but the rename happens in `migrations/m261102_170000_rename_tables_to_snake_case`,
  which runs **after** `m261102_142126_add_markdown_enabled_column` and
  `m261102_160000_add_enabled_to_structured_data_mappings` (migrations run in timestamp order:
  `142126 < 160000 < 170000`). On any install upgrading from before the rename, those two earlier
  migrations call `addColumn(Table::SEODATA, ...)` / `addColumn(Table::STRUCTUREDDATAMAPPINGS, ...)`
  against table names that **don't exist yet** (the old names are still live at that point).
  `columnExists()` silently returns `false` for a missing table (confirmed against
  `craft\db\Connection::columnExists()` — `isset()` on a null table schema just returns false rather
  than throwing), so `addColumn()` proceeds and fails with a SQL "table doesn't exist" error, halting
  the whole migration run.
  **Fix:** give the rename migration an earlier timestamp than both column-adding migrations, or fold
  all three into one migration that handles both old and new table names.
- **No HTTP timeout on either self-fetch call.** `MarkdownService::_generate()` and
  `SeoScoreCheckContext::getDocument()` both call `Craft::createGuzzleClient()->get($url)` with no
  `timeout`/`connect_timeout` option. A hairpin-NAT/firewall hiccup (common when a server calls its
  own public hostname) or a slow page hangs a queue worker indefinitely instead of failing fast.
- **Self-fetch doesn't account for staging auth.** Both of those same self-fetches will silently fail
  (401 / redirect-to-login) on any site sitting behind HTTP basic auth or a staging password gate
  (a common agency staging setup) — `MarkdownService` caches this as a generic failure
  (`NO_LLM_CONTENT_REASON` or the Guzzle exception message) rather than anything that points at the
  real cause.
- **CHANGELOG.md is dead.** It still says `v1.0.0-beta — Initial release`, but `@since` tags in the
  code go up to `v1.42.0` — there's no real version history anywhere, which will matter the moment
  this plugin is distributed via Composer/the Plugin Store and someone needs to know what changed
  between updates.
- **Regex redirect delimiter has no escaping.** `RedirectsService` builds patterns as
  `'#' . $sourceUri . '#'` (`findMatch()`, `findAllMatchingRedirects()`,
  `findNotFoundLogEntriesMatchedBy()`). A pattern containing a literal `#` fails to compile and gets
  rejected at save time with a generic "not a valid regular expression" error — not wrong, but a
  confusing dead end, since the real problem (delimiter collision) is invisible from the message.

## 2. Features already identified as missing (from the team's own roadmap docs)

Already tracked in `dev/*.md`, listed here as a direct answer to "what's missing":

- **SEO Score check #9 — broken-link detection.** The one checklist item still not built (needs a
  shared, TTL-cached per-URL result store across elements — materially bigger than the other checks).
- **Twitter `site`/`creator` handle fields** on `Settings` — no per-element input exists yet.
- **Search-engine verification meta tags** (`google-site-verification` etc.) — still requires
  hand-editing a template.
- **`llms.txt` free-text intro paragraph** and **optional URL prefix for `.md` routes** — both
  deliberately deferred, not forgotten.
- **Property-mapping UI isn't fully type-aware yet** (`FieldMappingUiElement` / Step 4 of the
  structured-data roadmap), **nested-type auto-scaffolding** (Step 7), and **cross-linking
  `StructuredData` objects** (Step 8) are all still open.
- **Commerce products/variants** aren't enumerated in the sitemap — explicitly deferred, not evaluated
  yet.
- **Element-index score column isn't sortable** — renders a percentage string with no numeric
  `ORDER BY` support.

## 3. Nice-to-haves (beyond what's already on the roadmap)

- **Settings per-check weight/threshold UI** for the SEO Score checklist (title/description length
  bounds, 300-word floor, image dimension minimum) — currently hardcoded constants, already flagged in
  `seo-score-checklist.md` as "not yet configurable."
- **A warm-cache console command for Markdown** (`core-seo-geo/markdown/warm`), mentioned in the
  roadmap — worth confirming it actually shipped, since without it the first bot visit after a deploy
  always pays the cold-fetch cost.
- **Admin-editable additional-AI-bots list**, flagged as recommended-yes in the roadmap — worth
  double-checking it shipped as a repeater rather than code-only.
- **Structured data validation hints** (Google rich-results required/recommended properties per type)
  — explicitly left as a "decide later."

## 4. Performance & security

### Performance

- `SitemapService::_assetsForElement()` issues a fresh `Asset::find()->relatedTo($element)` query
  **per element**, recursing into every nested Matrix entry at every depth — on a section with
  thousands of entries and nested content, this is a real N+1 pattern on cold-cache sitemap
  generation (the per-group cache hides it after the first hit, but that first hit on a large section
  could be slow/timeout-prone).
- `sitemap.xml` itself (`SitemapService::getIndexXml()`) is **never cached**, unlike every
  sub-sitemap — every request recomputes `getGroups()` (which walks all sections/categories and
  re-triggers the `EVENT_REGISTER_URLS` event) plus a `COUNT()` query per group. Cheap individually,
  but needless work on every crawler hit, and inconsistent with the caching discipline applied
  everywhere else in this service.
- `PluginTrait::registerSiteUrlRules()` calls `getTxtFiles()->getContent()` (a DB read) on **every
  single site request**, just to decide which URL rules to register — even on requests that will
  never touch a txt file. Worth confirming this is cheap/cached at the record layer; if not, it's a
  small tax on every page load.
- PHPStan is configured at **level 4** (`phpstan.neon`) — fairly low for a plugin this large; raising
  it incrementally would catch classes of bugs like the migration-ordering one above before they ship.

### Security

- No injection/eval/unserialize issues found; all raw SQL goes through parameterized
  `createCommand()`/`Expression()`. CSRF is left enabled everywhere it should be, and every
  admin-only controller action is gated with `requireAdmin(false)`/`requireCpRequest()` consistently.
- Admin-supplied regex in Redirects has no complexity/backtracking guard (ReDoS is self-inflicted-only
  since it's admin-only input, but worth a sanity note if this plugin is ever used in multi-admin/
  agency contexts where a non-technical admin could paste a pathological pattern).
- **Test coverage gap** (not a vulnerability, but relevant here): there are zero controller/feature
  tests — only `Unit/Models` and `Unit/Services`. Nothing exercises `requireAdmin`/
  `requirePostRequest` gating, the 404→redirect flow, or the mapping save/delete actions end-to-end,
  so a permission-check regression wouldn't be caught by `composer test`.
