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
 * Class m261021_120000_add_seo_data_table
 *
 * Adds the `coreseogeo_seodata` table for existing installs. See `Install.php` for the
 * canonical fresh-install shape, which creates the same table.
 *
 * Holds the title/description/robots/canonical configuration that used to live on `SeoField`'s
 * own settings, moved here so it's managed per section (plus one global bundle) per site instead
 * of per field - a site only ever needs one `SeoField` now, rather than one per section wanting
 * different settings.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class m261021_120000_add_seo_data_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function safeUp(): bool {
        if ($this->db->tableExists(Table::SEODATA)) {
            return true;
        }

        // `sectionHandle` uses `''` rather than `null` for the global bundle's row: a unique
        // index allows any number of `null`s through on both MySQL and Postgres, but only one
        // `''` per site, so it's what actually keeps a site down to one global bundle row.
        $this->createTable(Table::SEODATA, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'sectionHandle' => $this->string()->notNull()->defaultValue(''),
            'sectionLabel' => $this->string(),
            'titleConfig' => $this->json(),
            'descriptionConfig' => $this->json(),
            'robots' => $this->json(),
            'canonicalConfig' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::SEODATA, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::SEODATA, ['siteId', 'sectionHandle'], true);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::SEODATA);

        return true;
    }
}
