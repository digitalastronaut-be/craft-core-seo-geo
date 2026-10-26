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

use Carbon\Carbon;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use digitalastronaut\craftcoreseogeo\db\Table;

use yii\db\Query;

/**
 * Class m261024_170000_add_redirect_sites_table
 *
 * Adds the `coreseogeo_redirect_sites` table for existing installs, and migrates every
 * existing site-specific redirect's `siteId` column value into it before dropping that
 * column - see `Install.php` for the canonical fresh-install shape.
 *
 * A redirect used to apply to either every site (`siteId = null`) or exactly one
 * (`siteId = <id>`). This table lets it apply to any subset of sites instead: zero rows for
 * a redirect still means "every site" (the same meaning `siteId = null` had), while one row
 * per site it's scoped to replaces the single-`siteId` case - the same shape
 * `craft_elements_sites` uses for an element's own site propagation, but purpose-built here
 * rather than reusing that table, since a `Redirect` isn't a Craft element.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.19.0
 */
class m261024_170000_add_redirect_sites_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.19.0
     */
    public function safeUp(): bool {
        if (!$this->db->tableExists(Table::REDIRECTSITES)) {
            $this->createTable(Table::REDIRECTSITES, [
                'id' => $this->primaryKey(),
                'redirectId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, Table::REDIRECTSITES, ['redirectId'], Table::REDIRECTS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, Table::REDIRECTSITES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
            $this->createIndex(null, Table::REDIRECTSITES, ['redirectId', 'siteId'], true);
        }

        if ($this->db->columnExists(Table::REDIRECTS, 'siteId')) {
            $now = Db::prepareDateForDb(Carbon::now());

            $rows = (new Query())
                ->select(['id', 'siteId'])
                ->from(Table::REDIRECTS)
                ->where(['not', ['siteId' => null]])
                ->all($this->db);

            foreach ($rows as $row) {
                $this->insert(Table::REDIRECTSITES, [
                    'redirectId' => $row['id'],
                    'siteId' => $row['siteId'],
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ]);
            }

            $this->dropForeignKeyIfExists(Table::REDIRECTS, ['siteId']);
            $this->dropIndexIfExists(Table::REDIRECTS, ['siteId', 'sourceUri']);
            $this->dropColumn(Table::REDIRECTS, 'siteId');
            $this->createIndex(null, Table::REDIRECTS, ['sourceUri']);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.19.0
     */
    public function safeDown(): bool {
        $this->dropIndexIfExists(Table::REDIRECTS, ['sourceUri']);
        $this->addColumn(Table::REDIRECTS, 'siteId', $this->integer()->null()->after('id'));
        $this->addForeignKey(null, Table::REDIRECTS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::REDIRECTS, ['siteId', 'sourceUri'], false);

        $this->dropTableIfExists(Table::REDIRECTSITES);

        return true;
    }
}
