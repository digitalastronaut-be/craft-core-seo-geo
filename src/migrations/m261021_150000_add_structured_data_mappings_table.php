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
use craft\db\Table as CraftTable;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class m261021_150000_add_structured_data_mappings_table
 *
 * Adds the `coreseogeo_structureddatamappings` table for existing installs. See `Install.php`
 * for the canonical fresh-install shape, which creates the same table.
 *
 * Holds the schema.org type/property mapping configuration that used to live on
 * `StructuredDataField`'s own settings, moved here so a section can have any number of mappings
 * (e.g. a `Product` one and a `BreadcrumbList` one) managed per section (plus site-wide ones, via
 * `sectionHandle === ''`) per site, instead of one rigid mapping per field instance. The computed
 * JSON-LD values themselves stay on `coreseogeo_structureddata`, same as before.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.3.0
 */
class m261021_150000_add_structured_data_mappings_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function safeUp(): bool {
        if ($this->db->tableExists(Table::STRUCTUREDDATAMAPPINGS)) {
            return true;
        }

        // `sectionHandle` uses `''` rather than `null` for a site-wide mapping not tied to any
        // one section, same reasoning as `coreseogeo_seodata`'s global bundle row. Unlike that
        // table, more than one row per `(siteId, sectionHandle)` is expected - a section can have
        // several structured data mappings - so that pair isn't indexed as unique here.
        $this->createTable(Table::STRUCTUREDDATAMAPPINGS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'sectionHandle' => $this->string()->notNull()->defaultValue(''),
            'label' => $this->string(),
            'type' => $this->string()->notNull(),
            'properties' => $this->json(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::STRUCTUREDDATAMAPPINGS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::STRUCTUREDDATAMAPPINGS, ['siteId', 'sectionHandle']);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::STRUCTUREDDATAMAPPINGS);

        return true;
    }
}
