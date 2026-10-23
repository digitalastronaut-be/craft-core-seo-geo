<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\events;

use yii\base\Event;

/**
 * Class RegisterSitemapUrlsEvent
 *
 * `SitemapService::EVENT_REGISTER_URLS`. Lets a module contribute URLs the sitemap has no
 * other way to discover - most commonly a custom route (`config/routes.php` or a
 * `UrlManager::EVENT_REGISTER_SITE_URL_RULES` rule a module adds) whose dynamic segment is
 * resolved by that module's own controller against data this plugin can't see (an API, a
 * hardcoded list, anything that isn't a Craft element). Same shape as
 * `RegisterUrlRulesEvent::$rules` - a plain mutable array a listener appends to, not a
 * provider-class/interface, since there's nothing here that needs its own object per entry.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class RegisterSitemapUrlsEvent extends Event {
    /**
     * The site these URLs are being collected for. Listeners building multi-site URLs should
     * key off this rather than assuming the current site.
     *
     * @since v1.1.0
     */
    public int $siteId;

    /**
     * @var array<int, string|array{loc: string, lastmod: \DateTime|null}> each entry either a
     * bare absolute URL string, or `{loc, lastmod}` when a last-modified date is known.
     *
     * @since v1.1.0
     */
    public array $urls = [];
}
