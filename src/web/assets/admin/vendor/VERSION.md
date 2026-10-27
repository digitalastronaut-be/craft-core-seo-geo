# Vendored Datastar bundle

- **Source:** https://cdn.jsdelivr.net/gh/starfederation/datastar@v1.0.4/bundles/datastar-aliased.js
- **Version:** v1.0.4 (aliased build)
- **Fetched:** 2026-10-26

An ES module (ends in `export{...}`), imported for its side effects
(`admin.js`: `import "./vendor/datastar.js"`) - it scans the DOM for `data-*`
attributes on load, no explicit init call needed for declarative usage.

**Aliased, not the plain build** - Datastar is registered globally on every CP
page (`PluginTrait::registerCpAssets()`), so its scan isn't scoped to this
plugin's own markup. Craft core's element index stamps every table cell with
`data-attr="<attributeHandle>"` for its own bookkeeping (nothing to do with
Datastar), which collided head-on with Datastar's own `data-attr` directive -
Datastar saw `data-attr="uri"` on the URI column and tried to evaluate `uri`
as a JS expression, throwing on every element index showing that column. The
plain build's v1.0.4 has no supported way to exclude Craft's own markup from
its scan (no configurable prefix, and `data-ignore` has to sit on an
*ancestor* of the colliding element, which we don't control). The aliased
build sidesteps this by renaming every directive to `data-star-*`
(`data-star-on:click`, `data-star-show`, `data-star-attr`, ...), so Datastar
only ever reacts to attributes this plugin itself authors — see
[putyourlightson/craft-datastar-module](https://github.com/putyourlightson/craft-datastar-module),
which hits the exact same collision and takes the same approach. Every
`data-*` Datastar attribute this plugin renders (PHP and Twig both) uses the
`data-star-*` form accordingly - plain `data-*` attributes we author ourselves
for non-Datastar purposes (e.g. the element index score chip's
`data-element-id`) are left alone, since Datastar ignores anything outside
its own prefix now.

Vendored rather than loaded from the CDN at runtime, same reasoning as the
schema.org data: no external network dependency for the CP to render, and
Vite can bundle it together with our own JS. To upgrade, download the newer
tagged version's `datastar-aliased.js` (not `datastar.js`) from the same URL
pattern, overwrite `datastar.js`, and update this file's version/date.
