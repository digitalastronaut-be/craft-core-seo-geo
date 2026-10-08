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

use yii\base\InvalidArgumentException;

use Spatie\SchemaOrg\BaseType;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class CoreSeoGeoVariable
 *
 * `schema()` returns the live `spatie/schema-org` builder object itself, not JSON, so Twig's
 * attribute resolution can chain further property calls onto it (e.g.
 * `craft.coreSeoGeo.schema('Rating').ratingValue(5)`).
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
     * Constructs a `spatie/schema-org` builder for a schema.org type, for properties whose
     * expected value is itself a nested schema.org object rather than a plain string, e.g.
     * `craft.coreSeoGeo.schema('Rating').ratingValue(5).bestRating(5)` for a `Review`'s
     * `reviewRating` property.
     *
     * The type name is validated against our own vendored schema.org registry before
     * dispatching, so a typo or unsupported type raises a clear error instead of Twig's
     * generic "undefined method" when the chained property call fails.
     *
     * @param string $type a schema.org type name, e.g. `Rating`
     * @return BaseType
     * @throws InvalidArgumentException if `$type` isn't a known schema.org type, or has no
     * corresponding `spatie/schema-org` builder
     *
     * @see \digitalastronaut\craftcoreseogeo\services\SchemaOrgService::build()
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function schema(string $type): BaseType {
        return CoreSeoGeo::getInstance()->getSchemaOrg()->build($type);
    }
}
