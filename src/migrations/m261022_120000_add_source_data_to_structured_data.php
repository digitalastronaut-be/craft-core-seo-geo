<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\migrations;

use craft\db\Migration;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class m261022_120000_add_source_data_to_structured_data
 *
 * Adds `sourceData` to `coreseogeo_structureddata` - the raw form input a built-in standalone
 * type's settings page (Organization, WebSite, LocalBusiness, Person) was saved with, kept
 * separately from `properties` (the already-rendered schema.org shape `toJsonLd()` emits) so
 * re-opening the settings page can repopulate its fields - things like a logo's asset ID or an
 * address's individual parts aren't recoverable from the rendered `logo`/`address` values alone.
 * Null for every mapping-backed row (and any standalone row that predates this column), the same
 * way `properties` itself is empty before a mapping ever ran.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class m261022_120000_add_source_data_to_structured_data extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    public function safeUp(): bool {
        if ($this->db->columnExists(Table::STRUCTUREDDATA, 'sourceData')) {
            return true;
        }

        $this->addColumn(Table::STRUCTUREDDATA, 'sourceData', $this->json()->after('properties'));

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    public function safeDown(): bool {
        $this->dropColumn(Table::STRUCTUREDDATA, 'sourceData');

        return true;
    }
}
