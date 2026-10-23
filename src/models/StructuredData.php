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

/**
 * Class StructuredData
 *
 * `StructuredDataField`'s value: every `StructuredData` element already computed (by
 * `StructuredDataMappingService::syncEntry()`) for the owning entry/site, across every mapping
 * configured on that entry's section - a section with 3 mappings means 3 `$objects` entries,
 * not 1. There's nothing here a content editor can type into directly; the field is read-only
 * display, the same reasoning `JsonLdPreviewUiElement` already applies to a standalone object.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredData extends Model {
    /**
     * @var array<int, array<string, mixed>> Each already-computed object's full JSON-LD -
     * `@context`, `@type`, and every property - exactly as `elements\StructuredData::toJsonLd()`
     * produces it, one array per mapping that applies to this entry.
     *
     * @since v1.4.0 (replaces the single `$type`/`$data` pair a field-owned mapping used to need)
     */
    public array $objects = [];

    /**
     * @return bool
     * @since       v1.0.0
     */
    public function isEmpty(): bool {
        return $this->objects === [];
    }
}
