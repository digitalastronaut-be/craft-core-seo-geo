<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\structureddata;

/**
 * Interface StructuredDataTypeFieldsInterface
 *
 * Implemented by a small, hand-authored class per structured data type (e.g.
 * {@see WebPageFields}), listing the settings-page text fields `StructuredDataField` should
 * render for that type. Kept separate from the type's data model (e.g.
 * `\digitalastronaut\craftcoreseogeo\models\WebPage`) so the exposed fields, their labels, and
 * their instructions stay under full editorial control rather than being derived automatically
 * from the model's properties.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
interface StructuredDataTypeFieldsInterface {
    // Public Methods
    // =========================================================================

    /**
     * Returns the settings-page fields for this structured data type, in render order. Each
     * entry's `property` corresponds to a property on the type's data model. `default`, when
     * present, is the raw Twig template text pre-filled when no value has been configured yet
     * (e.g. `{{ entry.dateUpdated }}` or a plain `main`), since a lot of these properties are
     * the same for most Craft users.
     *
     * @return array<int, array{property: string, label: string, instructions: string, default?: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function fields(): array;
}
