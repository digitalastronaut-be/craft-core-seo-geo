# Structured Data Roadmap

Plan for moving beyond the current `WebPage`-only structured data support toward
full schema.org coverage with property mapping, nested objects, and cross-linking
between entries — informed by analysing what nystudio107/craft-seomatic does well
and where it falls short.

## Context (why)

- SEOmatic lets an editor pick *any* schema.org type via a "Main Entity of Page"
  dropdown, but that's cosmetic: it only relabels `@type`. The actual properties
  available are still whatever the underlying Craft/Commerce element's PHP class
  has a config for (`entrymeta`, `productmeta`, etc.). Picking "Review" on a plain
  entry produces a `Review` object with only `entrymeta`'s generic properties —
  `reviewRating`, `reviewBody`, etc. are silently absent.
- There is no CP UI in SEOmatic for mapping arbitrary properties to fields.
  Adding support for a type it doesn't ship a config for requires a developer
  writing PHP (a config override or an `EVENT_ADD_DYNAMIC_META` listener).
- Our opportunity: a real CP-driven property mapper, generic across all of
  schema.org, including nested object properties (e.g. `reviewRating: Rating`)
  and cross-references to other entries' structured data.

## Step 1 — Vendor the schema.org source data ✅ done

Pinned schema.org **v30.1**, **current** variant (active terms only, excludes
retired/"attic" terms), **https** URI scheme. Vendored at
`src/static/schemaorg/`:

- `schemaorg-current-https-types.csv`
- `schemaorg-current-https-properties.csv`
- `VERSION.md` — source URL, version, fetch date, upgrade instructions

**Format decision — CSV, not JSON-LD.** Checked the actual release files
before deciding: `-types.csv`'s `properties` column already ships every
type's full own + inherited property list, pre-resolved by schema.org's own
release pipeline (confirmed against `3DModel`, which correctly lists
`CreativeWork`-inherited properties like `headline`/`author`). JSON-LD only
gives the raw graph (a parent pointer per node, sometimes more than one) — we'd
have to write and maintain our own inheritance-flattening logic and risk not
matching schema.org's own resolution on multi-inheritance cases. Turtle/RDF-XML
/N-Triples/N-Quads carry the same raw-graph problem with harder parsing;
SHACL/ShEx/OWL are validation/reasoning formats, not property enumeration.
`properties.csv` supplies each property's `rangeIncludes` (expected value
types) and `comment`, joined by id at generator time.

## Step 2 — Write the generator command ✅ done

`php craft core-seo-geo/schema/generate` (`src/console/controllers/SchemaController.php`,
registered via `PluginTrait::registerEvents()`'s console-request check) compiles the vendored
CSVs into `src/static/schemaorg/registry.php`: **941 types, 1,468 properties**, 2.6MB.

- **Output shape:** one `var_export()`'d PHP array, not JSON or chunked files — OPcache-friendly,
  no decode step. Normalized into two top-level sections: `properties` (every definition stored
  once) and `types` (each listing property *names* only, inheritance pre-resolved). The first
  version inlined full property definitions into every type and bloated to 19.7MB on disk /
  107MB in memory from duplicated text (`name`/`description`/etc. repeated across hundreds of
  types); normalizing dropped that to 2.6MB / 16.5MB.
- **Multiple expected types** (e.g. `positiveNotes: ItemList|ListItem|Text|WebContent`): a plain
  string array under `expectedTypes`.
- **Enumeration member rows** (`Friday`, `LeftHandDriving`) excluded entirely — detected via a
  non-empty `enumerationtype` column, confirmed these are values, not pickable root types.
- **Scalars/DataTypes** (`Text`, `Number`, `Boolean`, ...) kept as normal type entries, just with
  an empty `properties` list — that emptiness is itself the signal a mapping UI can use to decide
  "plain value input" vs. "nested `schema()` scaffold," no separate flag needed.
- **Superseded properties** (`supersededBy` set) dropped entirely, not just hidden per-type.
- **Descriptions kept as raw HTML** (not stripped) since Craft tooltips render rich text — any
  plaintext need calls `strip_tags()` itself at that specific call site, not in the generator.
- **Relative `<a href="/...">` links absolutized** to `https://schema.org/...` in the generator
  itself (not deferred to render time) — verified against every `<a>` tag in both CSVs: every
  relative href is schema.org-internal and every external link is already absolute, so this has
  no false positives and no legitimate reason to ever stay relative, unlike the plaintext
  question above which is genuinely context-dependent.
- **`var_export()`'s native `array (...)` syntax kept as-is**, not reformatted to `[...]` —
  not worth a custom exporter just for style conformance on generated output.

## Step 3 — Expose the registry to the CP ✅ done

**No database tables** — considered and rejected storing the vocabulary as DB records for a
"free" query builder. It's read-only reference data (identical across every install of a given
plugin version, only changes when *we* bump the pinned schema.org version), not user content, so
it doesn't need Craft's query builder — that earns its keep on large, relational, mutable data,
none of which applies to 941 static rows. A DB approach would also still need the same vendored
files to seed from, plus a migration to keep in sync on every version bump — strictly more
ceremony than overwriting one file. The actual *user*-created data (which property maps to which
Twig template, per entry) already lives in the DB via existing Craft content storage, which is
correct and untouched by this decision.

- **PHP side:** `services/SchemaOrgService.php` (registered as `schemaOrg` in `ServicesTrait`,
  accessed via `CoreSeoGeo::getInstance()->getSchemaOrg()`). Lazy-loads and memoizes
  `registry.php` for the rest of the request. Exposes `getType()`, `getProperty()`,
  `getTypeWithProperties()` (names resolved into full definitions — what the mapping UI will
  actually render), and `getTypeOptions()` (name → label, for the flat-list picker). Verified
  end-to-end through the real Craft service container, not just in isolation.
- **CP JS side:** `web/assets/schema/SchemaAssetBundle.php`, registered alongside
  `AdminAssetBundle` in `PluginTrait::registerCpAssets()`. Publishes only `registry.json` (via
  `publishOptions.only`, even though `sourcePath` points at the whole `static/schemaorg/`
  folder) as a content-hashed CP resource — confirmed via Craft's asset manager that only
  `registry.json` gets published (not the CSVs/VERSION.md/registry.php), and confirmed it's
  reachable over HTTP with the right content type. Its published URL is injected as
  `window.CoreSeoGeoSchemaRegistryUrl` on every CP page load; nothing fetches it yet since the
  consuming UI doesn't exist until Step 4/7.
- **registry.json** is generated alongside `registry.php` in the same `SchemaController` run
  (same in-memory array, `Json::encode()` right after `var_export()`), so the two can't drift
  out of sync with each other.
- **Decided: ship the full registry as one cached asset**, not a lighter "names only" list with
  per-type lazy-fetching — simpler (one asset, no controller, no loading state), and 2.6MB
  cached after first load is acceptable for an admin tool.

## Step 4 — Make the property mapping UI type-aware

Today `FieldMappingUiElement` assumes `WebPage`. Change it to read the registry
for whichever schema.org type is currently selected (Main Entity of Page or a
standalone `StructuredData` element's type) and render one row per property.

**Decide:**
- How existing `WebPage`-only data migrates (keep working, or require
  re-mapping).
- How the row list behaves for very large types (pagination, search/filter
  within the property list).

## Step 5 — Bind `spatie/schema-org` into Twig ✅ done

Added `spatie/schema-org` (`^4.0.2`, the last line compatible with the
project's pinned PHP 8.2 platform; v5 requires PHP 8.4) to the plugin's
`composer.json`. `craft.coreSeoGeo.schema(type)` (via `CoreSeoGeoVariable`)
dispatches to `Spatie\SchemaOrg\Schema::{$method}()` through a new
`SchemaOrgService::build()`. Twig's native attribute resolution handles the
rest of the chaining (`.ratingValue().bestRating()`) for free, no custom Twig
extension needed beyond this one entry point. `schema()` returns the live
builder object itself (not JSON-encoded like this variable's other methods),
since it needs to support further chained property calls; turning the final
result into JSON is Step 6's job.

- **Type name to spatie class name:** confirmed by diffing every installed
  class's `getType()` literal against its class name that they're identical
  for all 921 shipped types except one: `3DModel`, renamed to
  `ThreeDimensionalModel` since PHP class names can't start with a digit.
  `SchemaOrgService::TYPE_CLASS_OVERRIDES` holds just that one exception
  rather than a general slugify pass. `Schema`'s static factory method name
  is `lcfirst()` of the class name.
- **Validated the type name against our own registry before dispatching**,
  as this step's "Decide" called for: `build()` throws a
  `yii\base\InvalidArgumentException` naming the type if it's not in our
  registry at all, and a second, distinct message if it's a registry type
  with no corresponding spatie builder (confirmed 29 such gaps: the scalar
  `DataType`s we deliberately keep in the registry per Step 2, like `Text`/
  `Number`/`Boolean`, plus a handful of schema.org types newer than the
  schema.org version the installed spatie package was generated from, e.g.
  `DigitalProductPassport`). Both are friendlier than Twig's generic
  "undefined method" once a chained property call fails.

## Step 6 — Define the template-value evaluation convention

Each property's stored value is still a Twig template string (same shape as
today). After rendering, attempt `Json::decode()` on the result before merging
into the final properties array.

**Decide:**
- Exact place this decode step lives (e.g. a method on `StructuredDataService`).
- What happens on decode failure — treat as a plain string (safe default) vs.
  surface a validation error.

## Step 7 — Auto-scaffold nested-type skeletons in the CP

When a property's expected type is itself a schema.org type (not a scalar),
show a checkbox picker (reusing the registry) listing that type's own
properties. On confirm, insert `schema('rating').ratingValue().bestRating()`
into the template field automatically.

**Decide:**
- Checkbox picker (explicit, scales to all types) vs. a small hand-curated
  default set for common types (faster UX, doesn't scale) — or both, picker
  first with curated presets as a shortcut later.
- UI placement: inline under the mapping row vs. a modal/HUD.

## Step 8 — Cross-link existing `StructuredData` objects

Let one object reference another (e.g. a Review's `itemReviewed` pointing at a
Product entry's own computed structured data) via
`craft.coreSeoGeo.getSchemas([id, id])`, plus a companion
`getSchemaForElement($element)` for the common case of "give me the structured
data attached to this other entry" using the existing `ownerId`/`fieldId`
columns.

**Decide:**
- Reference (`{"@id": "..."}`) vs. full inline embed. (Recommendation: embed in
  full for now — Google only reads what's actually on the page — but assign a
  stable `@id` to every `StructuredData` object so real graph-wide
  de-duplication can be added later without a data migration.)
- Stable `@id` format (tied to element UID vs. numeric ID/URL) — lock this in
  now since it's painful to change after objects are already referencing it.
- Whether `getSchemas()` should respect draft/status visibility rules.

## Step 9 (stretch) — Google rich-result hints

SEOmatic ships a hand-curated required/recommended property list per type for
validation warnings; schema.org/Google don't publish this as structured data.

**Decide:**
- Skip entirely for v1, or hand-curate a short list for the handful of types
  people actually care about (Review, Product, Article, Event, FAQPage,
  LocalBusiness).

## Step 10 — Document the upgrade workflow

Write down how to bump the pinned schema.org version later: re-run the Step 2
generator against a newer vendored release file, review the registry diff,
commit.

**Decide:**
- Cadence (react to schema.org releases as they land vs. periodic check).
- Where this process gets documented (this file vs. a CHANGELOG entry
  convention).
