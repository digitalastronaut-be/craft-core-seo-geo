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
 * Class m261006_120000_create_structureddata_table
 *
 * Creates the `structureddata` table for installs that already exist from before the
 * `StructuredData` element type was added - `Install.php` only runs for brand new installs.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class m261006_120000_create_structureddata_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function safeUp(): bool {
        if ($this->db->tableExists(Table::STRUCTUREDDATA)) return true;

        $this->createTable(Table::STRUCTUREDDATA, [
            'id' => $this->integer()->notNull(),
            'type' => $this->string()->notNull(),
            'properties' => $this->json()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::STRUCTUREDDATA);

        return true;
    }
}
