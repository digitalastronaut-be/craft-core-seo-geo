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
 * Class m261021_160000_drop_site_id_from_structured_data_mappings
 *
 * Drops `siteId` from `coreseogeo_structureddatamappings` for existing installs. See
 * `Install.php` for the canonical fresh-install shape, which no longer creates that column at
 * all.
 *
 * A structured data mapping's type/property templates already resolve per element - and so,
 * through the element, per site - the same way a `SeoField`'s title/description templates do.
 * There's nothing site-specific about the mapping *configuration* itself, so unlike
 * `coreseogeo_seodata` (one row per section per site), a mapping only ever needs one row per
 * section.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.3.0
 */
class m261021_160000_drop_site_id_from_structured_data_mappings extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function safeUp(): bool {
        if (!$this->db->columnExists(Table::STRUCTUREDDATAMAPPINGS, 'siteId')) {
            return true;
        }

        $this->dropForeignKeyIfExists(Table::STRUCTUREDDATAMAPPINGS, ['siteId']);
        $this->dropIndexIfExists(Table::STRUCTUREDDATAMAPPINGS, ['siteId', 'sectionHandle']);
        $this->dropColumn(Table::STRUCTUREDDATAMAPPINGS, 'siteId');
        $this->createIndex(null, Table::STRUCTUREDDATAMAPPINGS, ['sectionHandle']);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function safeDown(): bool {
        $this->dropIndexIfExists(Table::STRUCTUREDDATAMAPPINGS, ['sectionHandle']);
        $this->addColumn(Table::STRUCTUREDDATAMAPPINGS, 'siteId', $this->integer()->notNull()->after('id'));
        $this->addForeignKey(null, Table::STRUCTUREDDATAMAPPINGS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::STRUCTUREDDATAMAPPINGS, ['siteId', 'sectionHandle']);

        return true;
    }
}
