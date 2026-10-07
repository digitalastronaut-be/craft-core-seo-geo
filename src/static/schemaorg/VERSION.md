# Vendored schema.org data

- **Source:** https://github.com/schemaorg/schemaorg/tree/main/data/releases/30.1
- **Version:** 30.1
- **Variant:** `current` (active terms only — excludes retired/"attic" terms), `https` URI scheme
- **Fetched:** 2026-10-07

Two files, joined by property/type id at generator time:

- `schemaorg-current-https-types.csv` — one row per type: `subTypeOf`, and a
  `properties` column that already lists every own + inherited property
  (schema.org's own release pipeline resolves inheritance for us).
- `schemaorg-current-https-properties.csv` — one row per property:
  `comment`, `domainIncludes`, `rangeIncludes` (expected value types).

To upgrade: download the same two files from a newer `data/releases/{version}/`
directory, overwrite these, update this file's version/date, then re-run the
registry generator (Step 2) and review the diff.
