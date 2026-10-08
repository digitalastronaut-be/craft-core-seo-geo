# Vendored Datastar bundle

- **Source:** https://cdn.jsdelivr.net/gh/starfederation/datastar@v1.0.4/bundles/datastar.js
- **Version:** v1.0.4
- **Fetched:** 2026-10-07

An ES module (ends in `export{...}`), imported for its side effects
(`admin.js`: `import "./vendor/datastar.js"`) - it scans the DOM for `data-*`
attributes on load, no explicit init call needed for declarative usage.

Vendored rather than loaded from the CDN at runtime, same reasoning as the
schema.org data: no external network dependency for the CP to render, and
Vite can bundle it together with our own JS. To upgrade, download a newer
tagged version from the same URL pattern, overwrite `datastar.js`, and update
this file's version/date.
