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

use ArrayObject;

use craft\helpers\Json;

/**
 * Class SchemaList
 *
 * What `StructuredDataService::getSchemas()` returns: a list of already-cleaned schema.org
 * arrays that stringifies to its own JSON array when printed bare in a mapping property
 * template (`{{ craft.coreSeoGeo.getSchemas(...) }}`), the same "prints as the right JSON on
 * its own" trick `spatie/schema-org`'s `BaseType::__toString()` gives a single object - just
 * for a list of them instead of one, so a `review`-style list property needs no `|json_encode`.
 *
 * Still `ArrayObject`, not a plain string, for any caller that wants the data itself (countable,
 * iterable, array-accessible) rather than its JSON string.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.40.0
 */
class SchemaList extends ArrayObject {
    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function __toString(): string {
        return Json::encode($this->getArrayCopy(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
