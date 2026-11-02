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

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\Tip;

use yii\base\InvalidArgumentException;

use Spatie\SchemaOrg\BaseType;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\SchemaList;
use digitalastronaut\craftcoreseogeo\models\Settings;
use digitalastronaut\craftcoreseogeo\services\JsonLdGraphService;

/**
 * Class CoreSeoGeoVariable

 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class CoreSeoGeoVariable {
    // Public Methods
    // =========================================================================

    /**
     * @param string $type
     * @return BaseType
     * @throws InvalidArgumentException
     *
     * @see \digitalastronaut\craftcoreseogeo\services\SchemaOrgService::build()
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function createSchema(string $type): BaseType {
        return CoreSeoGeo::getInstance()->getSchemaOrg()->build($type);
    }

    /**
     * Looks up the already-computed `StructuredData` row for `$element`/`$type` and rebuilds it
     * into a `spatie/schema-org` object, so a template composing a bigger structure (e.g. a
     * `Product`'s `review` list) can reuse an entry's own already-rendered structured data
     * instead of re-deriving it from that entry's raw fields.
     *
     * @param ElementInterface $element
     * @param string $type a schema.org type name, e.g. `Review`
     * @return BaseType
     * @throws InvalidArgumentException if no computed `StructuredData` row of this `$type`
     * exists for `$element` - never `null`, so a stale/missing mapping fails loudly instead of
     * silently rendering incomplete structured data
     *
     * @see \digitalastronaut\craftcoreseogeo\services\StructuredDataService::getSchema()
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function getSchema(ElementInterface $element, string $type): BaseType {
        return CoreSeoGeo::getInstance()->getStructuredData()->getSchema($element, $type);
    }

    /**
     * `getSchema()` for every element in `$elements`, wrapped in a `SchemaList` so a template
     * building a list property can print this bare - no `|json_encode` needed, the same as a
     * single `createSchema(...)`/`getSchema()` object.
     *
     * @param iterable<ElementInterface> $elements
     * @param string $type a schema.org type name, e.g. `Review`
     * @return SchemaList
     * @throws InvalidArgumentException as soon as any element in `$elements` has no computed
     * `StructuredData` row of this `$type`
     *
     * @see \digitalastronaut\craftcoreseogeo\services\StructuredDataService::getSchemas()
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function getSchemas(iterable $elements, string $type): SchemaList {
        return CoreSeoGeo::getInstance()->getStructuredData()->getSchemas($elements, $type);
    }

    /**
     * @param array<int, array{label: string, href: string}> $items ordered from just below the
     * homepage to the current page
     * @param bool $includeHomepage
     * @return array<int, array{'@type': string, position: int, name: string, item: string}>
     *
     * @see \digitalastronaut\craftcoreseogeo\services\SchemaOrgService::breadcrumbs()
     * @author      Digitalastronaut
     * @since       v1.7.0
     */
    public function breadcrumbs(array $items, bool $includeHomepage = true): array {
        return CoreSeoGeo::getInstance()->getSchemaOrg()->breadcrumbs($items, $includeHomepage);
    }

    /**
     * @return JsonLdGraphService the request-scoped JSON-LD `@graph` templates contribute
     * structured data objects to - see `JsonLdGraphService::render()`, called once at the end
     * of `_base/scripts.twig`.
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function graph(): JsonLdGraphService {
        return CoreSeoGeo::getInstance()->getJsonLdGraph();
    }

    /**
     * @return Settings
     *
     * @author      Digitalastronaut
     * @since       v1.1.1
     */
    public function settings(): Settings {
        return CoreSeoGeo::getInstance()->getSettings();
    }

    /**
     * The label for this plugin's own CP nav item, respecting the `Settings::$pluginName`
     * override exactly like `CoreSeoGeo::getCpNavItem()` does. Used as every CP page's root
     * breadcrumb label so it can never drift out of sync with the nav item itself.
     *
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function rootLabel(): string {
        $item = CoreSeoGeo::getInstance()->getCpNavItem();

        return $item['label'] ?? Craft::t('core-seo-geo', 'SEO/GEO');
    }

    /**
     * Renders the unresolved-custom-routes warning for the sitemap settings (now part of the
     * Global settings page), using Craft's own built-in `Tip` field-layout UI element so it
     * matches every other warning callout in the CP exactly. `null` when there's nothing to warn
     * about. Moved here from the now-retired `SettingsController` - a plain `['template' => ...]`
     * CP URL rule has no controller action to build this in PHP and pass down, so the template
     * calls this directly instead.
     *
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function unresolvedSitemapRoutesWarningHtml(): ?string {
        $patterns = CoreSeoGeo::getInstance()->getSitemap()->getUnresolvedRoutePatterns();

        if ($patterns === []) return null;

        $list = implode("\n", array_map(
            static fn(string $pattern): string => "- `{$pattern}`",
            $patterns,
        ));

        $tip = new Tip([
            'style' => Tip::STYLE_WARNING,
            'tip' => Craft::t('core-seo-geo', "The custom route patterns below (from `config/routes.php` or the Routes section under Settings) include a dynamic segment, so they can't be resolved to real URLs automatically and won't be included in the sitemap:\n\n{list}\n\nTo add their real URLs yourself, listen for `SitemapService::EVENT_REGISTER_URLS` and append to the event's `urls` array - this plugin has no way to know what a dynamic route like this actually resolves to on its own. Call `SitemapService::clearCustomUrlsCache()` after your own data changes, since nothing about that event lets the sitemap's cache invalidate itself automatically.", [
                'list' => $list,
            ]),
        ]);

        return $tip->formHtml();
    }
}
