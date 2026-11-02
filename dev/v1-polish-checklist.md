# v1 Polish Checklist

Gaps found comparing the current `<head>` output and CP feature set against SEOmatic
(installed side by side in this project for reference) and against the agency's own
`go-live-checklist.md`, which still lists several of these as manual per-launch steps.
Ordered by importance.

- [ ] **Redirects** — `src/templates/redirects/_index.twig` is a 4-line stub; there's no
  `RedirectsService`, record, migration, or 404-catching route. Source → destination
  mapping, 301/302 toggle, hit counter, CP table UI. The single biggest gap — also the
  one manual step (`Redirects nodig voor oude website?`) still on the go-live checklist.
  Full plan: `redirects-roadmap.md`.
- [ ] **robots.txt management** — nothing in the plugin generates or edits it today.
  CP-editable robots.txt that auto-includes the sitemap URL, closing the
  `robots.txt controleren / Sitemap includen` go-live step.
- [x] **`hreflang="x-default"`** — `HreflangService::getAlternates()` now appends an
  `x-default` entry pointing at the install's primary site, falling back to the first
  resolved alternate when the primary site isn't among the element's own supported
  sites (or has no localized copy there).
- [x] **`og:locale` + `og:locale:alternate`** — emitted in `_tags.twig` from the same
  `hreflangAlternates` data, converted from hyphenated to underscore locale codes.
- [x] **`og:image:width` / `og:image:height` / `og:image:alt`** — emitted in
  `_tags.twig` from the already-in-scope `Asset` object (Craft 5's native `alt`
  attribute).
- [ ] **`twitter:site` / `twitter:creator`** — needs a new `Settings` field (e.g.
  `$twitterHandle`); no per-element input exists for it today.
- [ ] **Site-wide staging noindex kill-switch** — a single `Settings` toggle forcing
  `robots: noindex` globally, independent of per-page settings. CP-visible version of
  the `CRAFT_DISALLOW_ROBOTS` env var already in the go-live checklist.
- [ ] **Search engine verification meta tags** — `Settings` fields (e.g.
  `googleSiteVerification`) rendered as `<meta name="google-site-verification">`.
  Commonly needed at launch, currently requires hand-editing the template.
- [x] **`theme-color` + referrer policy** — `templates/_base/head.twig` hardcoded
  `<meta name="referrer" content="no-referrer">` (overly strict — drops referrer data
  even between two HTTPS sites) and `<meta name="theme-color" content="">` (shipping
  empty/broken). Both now driven by new `Settings::$referrerPolicy` /
  `Settings::$themeColor` fields (Global SEO/GEO settings page, "Miscellaneous"
  section), exposed to site templates via `craft.coreSeoGeo.settings()`.
  `themeColor` also feeds `manifest.webmanifest`'s `theme_color`/`background_color`
  (`FaviconService::getManifest()`), which had neither key before.
