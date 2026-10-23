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
 * Class m261017_120000_add_seo_score_table
 *
 * Adds the `coreseogeo_seoscores` table for existing installs. See `Install.php` for the
 * canonical fresh-install shape, which creates the same table.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class m261017_120000_add_seo_score_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function safeUp(): bool {
        if ($this->db->tableExists(Table::SEOSCORE)) {
            return true;
        }

        $this->createTable(Table::SEOSCORE, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'fieldId' => $this->integer()->notNull(),
            'score' => $this->smallInteger(),
            'pending' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::SEOSCORE, ['elementId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::SEOSCORE, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::SEOSCORE, ['fieldId'], CraftTable::FIELDS, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::SEOSCORE, ['elementId', 'siteId', 'fieldId'], true);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::SEOSCORE);

        return true;
    }
}
