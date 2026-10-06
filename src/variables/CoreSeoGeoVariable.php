<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\variables;

use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class CoreSeoGeoVariable
 *
 * Every method here returns an already-encoded JSON string (compact, unescaped slashes), not
 * a PHP array, so a dev can drop it straight into a property's template with no `|json_encode`
 * of their own, e.g. `{{ craft.coreSeoGeo.createWebPageElement('main') }}`.
 *
 * A `StructuredDataField` property's own template is rendered via `renderObjectTemplate()`,
 * which exposes the element's own attributes as bare variables (`title`, `url`, ...), not
 * under an `entry` variable like a normal site template would.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class CoreSeoGeoVariable {
    // Public Methods
    // =========================================================================

    /**
     * Builds a schema.org `BreadcrumbList` object, usable as-is for the `breadcrumb` property,
     * e.g. `craft.coreSeoGeo.createBreadcrumbs([{ label: title, href: url }])` inside a
     * `StructuredDataField` property's template.
     *
     * @param array<int, array{label: string, href: string}> $items The breadcrumbs, from the
     * one right under "Home" to the current page, in order.
     * @param string|null $homeUrl The "Home" crumb's URL. Defaults to the current site's base
     * URL.
     * @param string|null $homeLabel The "Home" crumb's label.
     * @return string The `BreadcrumbList` object, JSON-encoded.
     *
     * @see \digitalastronaut\craftcoreseogeo\services\StructuredDataService::createBreadcrumbs()
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function createBreadcrumbs(array $items, ?string $homeUrl = null, ?string $homeLabel = null): string {
        return Json::encode(
            CoreSeoGeo::getInstance()->getStructuredData()->createBreadcrumbs($items, $homeUrl, $homeLabel),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Builds a schema.org `WebPageElement` object, usable as-is for the `mainContentOfPage`
     * property, e.g. `craft.coreSeoGeo.createWebPageElement('main')`.
     *
     * @param string|null $cssSelector A CSS selector identifying the element, e.g. `main` or
     * `#content`.
     * @param string|null $xpath An XPath identifying the element.
     * @return string The `WebPageElement` object, JSON-encoded.
     *
     * @see \digitalastronaut\craftcoreseogeo\services\StructuredDataService::createWebPageElement()
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function createWebPageElement(?string $cssSelector = null, ?string $xpath = null): string {
        return Json::encode(
            CoreSeoGeo::getInstance()->getStructuredData()->createWebPageElement($cssSelector, $xpath),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
