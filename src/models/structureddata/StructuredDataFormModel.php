<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models\structureddata;

/**
 * Interface StructuredDataFormModel
 *
 * The contract every built-in standalone type's settings-page model (`OrganizationData` and
 * friends) implements - `GlobalStructuredDataService` only ever talks to these through this
 * interface, not any particular one of them.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
interface StructuredDataFormModel {
    /**
     * Builds this object's schema.org properties from the model's own fields - the fixed,
     * code-only mapping behind this built-in type.
     *
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    public function toProperties(): array;
}
