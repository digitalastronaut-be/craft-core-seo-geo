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
 * Class m261024_160000_add_redirect_hits_table
 *
 * Adds the `coreseogeo_redirect_hits` table for existing installs - one row per 5-minute
 * bucket per site that's served at least one redirect, with a running `hitCount`. See
 * `Install.php` for the canonical fresh-install shape, and `RedirectHit`'s own docblock for
 * why this is a separate table from `coreseogeo_redirects` rather than a column on it.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.17.0
 */
class m261024_160000_add_redirect_hits_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.17.0
     */
    public function safeUp(): bool {
        if (!$this->db->tableExists(Table::REDIRECTHITS)) {
            $this->createTable(Table::REDIRECTHITS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'timestamp' => $this->dateTime()->notNull(),
                'hitCount' => $this->integer()->notNull()->defaultValue(1),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, Table::REDIRECTHITS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
            $this->createIndex(null, Table::REDIRECTHITS, ['siteId', 'timestamp'], true);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.17.0
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::REDIRECTHITS);

        return true;
    }
}
