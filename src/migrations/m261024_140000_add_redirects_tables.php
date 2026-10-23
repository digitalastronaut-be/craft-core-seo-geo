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
use digitalastronaut\craftcoreseogeo\records\Redirect;

/**
 * Class m261024_140000_add_redirects_tables
 *
 * Adds the `coreseogeo_redirects` and `coreseogeo_404s` tables for existing installs.
 * See `Install.php` for the canonical fresh-install shape, which creates the same tables.
 *
 * `coreseogeo_redirects` holds every registered redirect, exact or regex alike
 * (`matchType`), manually added or auto-created from an entry's URL changing
 * (`sourceElementId`). `coreseogeo_404s` holds one row per distinct 404'd URI that
 * matched none of them - see `redirects-roadmap.md` Step 1 for why this is two tables
 * instead of Retour's three.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
class m261024_140000_add_redirects_tables extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function safeUp(): bool {
        if (!$this->db->tableExists(Table::REDIRECTS)) {
            $this->createTable(Table::REDIRECTS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->null(),
                'matchType' => $this->string(10)->notNull()->defaultValue(Redirect::MATCH_TYPE_EXACT),
                'sourceUri' => $this->string(255)->notNull(),
                'destinationUri' => $this->string(255)->null(),
                'statusCode' => $this->smallInteger()->notNull()->defaultValue(Redirect::STATUS_CODE_MOVED_PERMANENTLY),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'sourceElementId' => $this->integer()->null(),
                'hitCount' => $this->integer()->notNull()->defaultValue(0),
                'dateLastHit' => $this->dateTime()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, Table::REDIRECTS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, Table::REDIRECTS, ['sourceElementId'], CraftTable::ELEMENTS, ['id'], 'SET NULL', null);
            $this->createIndex(null, Table::REDIRECTS, ['siteId', 'sourceUri'], false);
        }

        if (!$this->db->tableExists(Table::NOTFOUNDLOG)) {
            $this->createTable(Table::NOTFOUNDLOG, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'uri' => $this->string(255)->notNull(),
                'referrer' => $this->string(2000)->null(),
                'hitCount' => $this->integer()->notNull()->defaultValue(1),
                'dateLastHit' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, Table::NOTFOUNDLOG, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
            $this->createIndex(null, Table::NOTFOUNDLOG, ['siteId', 'uri'], true);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::REDIRECTS);
        $this->dropTableIfExists(Table::NOTFOUNDLOG);

        return true;
    }
}
