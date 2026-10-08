<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models;

use craft\base\Model;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class StructuredData
 *
 * The computed structured data for one element: `type` and `data` are derived entirely from
 * the field's `properties` settings rendered against the element, there's nothing left here
 * that a content editor can type into directly.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredData extends Model {
    /**
     * @var string|null The schema.org type this object represents, e.g. `WebPage`.
     *
     * @since v1.0.0
     */
    public ?string $type = null;

    /**
     * @var array<string, mixed> The computed value for each of the type's configured
     * properties, keyed by property name.
     *
     * @since v1.0.0
     */
    public array $data = [];

    /**
     * @return bool
     * @since       v1.0.0
     */
    public function isEmpty(): bool {
        return $this->data === [];
    }

    /**
     * Returns the full JSON-LD object: `@context`, `@type`, and every computed property.
     *
     * @return array<string, mixed>
     *
     * @since v1.0.0
     */
    public function toJsonLd(): array {
        return CoreSeoGeo::getInstance()->getStructuredData()->toJsonLd((string)$this->type, $this->data);
    }
}
