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
 * Class m261024_110000_add_image_id_to_seo_data
 *
 * Adds `imageId` to `coreseogeo_seodata` for existing installs - the section (and global bundle)
 * level fallback image a `SeoField`'s own per-entry image falls back to when it's left empty. See
 * `Install.php` for the canonical fresh-install shape. `SET NULL` on delete rather than `CASCADE`:
 * the chosen asset is only a reference this row falls back to, not something the row belongs to -
 * deleting it shouldn't delete the row.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.9.0
 */
class m261024_110000_add_image_id_to_seo_data extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.9.0
     */
    public function safeUp(): bool {
        if ($this->db->columnExists(Table::SEODATA, 'imageId')) {
            return true;
        }

        $this->addColumn(Table::SEODATA, 'imageId', $this->integer()->after('canonicalConfig'));
        $this->addForeignKey(null, Table::SEODATA, ['imageId'], CraftTable::ELEMENTS, ['id'], 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.9.0
     */
    public function safeDown(): bool {
        $this->dropForeignKeyIfExists(Table::SEODATA, ['imageId']);
        $this->dropColumn(Table::SEODATA, 'imageId');

        return true;
    }
}
