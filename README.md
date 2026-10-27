# Core SEO/GEO

A no-fluff SEO/GEO (Generative Engine Optimization) solution for Craft CMS websites — on-page meta, structured data, sitemaps, redirects, and AI-crawler support, built in as first-class Craft fields and services rather than a single catch-all settings blob.

## Features

### Meta & on-page SEO
- **SEO field** for titles, descriptions, social image/card overrides, canonical URL, and indexing (`robots`) control, with format strings (`{title} | {siteName}`) configurable per-section and falling back to a global default.
- **SEO Score** — a per-entry checklist (title/description length and uniqueness, heading structure, image alt text, canonical correctness, duplicate content, social tags, and more) that runs as a background queue job and surfaces as a score with pass/fail/warning detail on every element.
- **hreflang** alternates (including `x-default`) generated automatically for multi-site installs, plus matching `og:locale` / `og:locale:alternate` output.
- Open Graph and Twitter card tags, including image width/height/alt, generated from the asset already in scope — no extra fields to fill in.
- Site-wide **referrer policy** and **theme-color** settings, also reflected in the web app manifest.

### Structured data (JSON-LD)
- A **type/property mapping UI** per section, backed by the full [schema.org](https://schema.org) vocabulary (vendored and compiled via a console command), so editors map entry fields to schema properties without touching code.
- Mapped data is kept in sync automatically as entries are saved, with a console command (`structured-data/sync`) to backfill existing content or resync on demand.
- Global, site-wide structured data for **Organization**, **LocalBusiness** (with address), **Person**, and **WebSite**, editable from the control panel.
- A request-scoped JSON-LD `@graph` builder (`craft.coreSeoGeo.graph()`) that templates contribute to, plus a `craft.coreSeoGeo.schema()` helper for building any schema.org type and a `breadcrumbs()` helper for `BreadcrumbList` output — all rendered as a single `<script type="application/ld+json">` block.

### Sitemaps & indexing
- An automatic **XML sitemap** covering entries, categories, and assets, with support for custom/static URLs and a `EVENT_REGISTER_URLS` hook for dynamic routes the plugin can't resolve on its own.
- CP-editable **robots.txt** that includes the sitemap URL automatically, with a per-rule allow/disallow table and an optional control-panel-path disallow.
- A site-wide **noindex kill switch** for staging environments, independent of any per-page setting.

### Redirects & 404 tracking
- Source → destination redirects with 301/302 toggle, CP table UI, and automatic matching against incoming 404s.
- A 404 hit log and redirect hit counters, so dead links and redirect usage are visible without digging through server logs.

### AI crawlers & GEO (llms.txt)
- **`llms.txt`** and **`llms-full.txt`** generation, including a per-entry/per-section Markdown representation of a page's content — built by fetching the page's own rendered output and converting the regions marked with the `{% llmContent %}` / `{% excludeLlmContent %}` Twig tags, cached and invalidated the same way Craft's own `{% cache %}` tag is.
- **AI bot detection** against a bundled list of known AI crawler/agent user-agent strings, for templates or logic that want to treat them differently (e.g. serving the Markdown view).

### Other text files & tracking
- CP-editable **humans.txt**, **ads.txt**, **security.txt**, and **trust.txt**.
- A **favicon/app icon generator** that derives every size and format a site needs (favicon, Apple touch icon, Android icons, web app manifest) from a single source image via Craft's own asset transforms.
- A **tracking scripts** manager for head/body snippets (GTM, Meta Pixel, etc.) without editing templates per deploy.

## Requirements

This plugin requires Craft CMS 5.11.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project's Control Panel and search for "Core SEO/GEO". Then press "Install".

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require digitalastronaut/craft-core-seo-geo

# tell Craft to install the plugin
./craft plugin/install core-seo-geo
```

## Usage

Most configuration happens in the control panel under **SEO/GEO** — Global, Content, Tracking Scripts, Redirects, Structured Data, and Appearance.

In templates, the plugin exposes a `craft.coreSeoGeo` variable:

```twig
{# build any schema.org type #}
{% set article = craft.coreSeoGeo.schema('Article') %}

{# contribute to the page's JSON-LD @graph #}
{% do craft.coreSeoGeo.graph().add(article) %}

{# breadcrumbs as schema.org BreadcrumbList data #}
{% set breadcrumbs = craft.coreSeoGeo.breadcrumbs(items) %}

{# read plugin settings #}
{% set settings = craft.coreSeoGeo.settings() %}
```

Mark the regions of a template that count as real content for the `llms.txt`/Markdown output:

```twig
{% llmContent %}
    {# this region is included #}
{% endllmContent %}

{% excludeLlmContent %}
    {# this region (e.g. nav, footer) is excluded #}
{% endexcludeLlmContent %}
```

### Console commands

```bash
# backfill/resync structured data for every mapped section (or one section's handle)
php craft core-seo-geo/structured-data/sync [sectionHandle]

# regenerate the compiled schema.org registry after vendoring a newer release
php craft core-seo-geo/schema/generate
```

## Development

```bash
composer install
npm install

composer check-cs   # ECS code style check
composer fix-cs      # ECS code style fix
composer phpstan     # static analysis
composer test        # Pest test suite

npm run plugin:dev   # Vite dev server for CP assets
npm run plugin:build  # production build of CP assets
```

## Support

- Issues: https://github.com/digitalastronaut-be/craft-core-seo-geo/issues
- Email: bram@digitalastronaut.be
