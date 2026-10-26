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
use craft\helpers\StringHelper;

use digitalastronaut\craftcoreseogeo\db\Table;

use yii\db\Query;

/**
 * Class m261026_100000_add_metrics_table
 *
 * Replaces `coreseogeo_404_hits`/`coreseogeo_redirect_hits` with the single, generic
 * `coreseogeo_metrics` table (one row per 5-minute bucket per `(type, entityId, siteId)`,
 * covering both tables' old hit-volume rows plus the new "new 404"/"new redirect" counts that
 * used to be derived from `dateCreated` group-bys instead of being recorded as metrics in
 * their own right). See `Metric`'s own docblock for why one table now covers all four, and
 * `Install.php` for the canonical fresh-install shape.
 *
 * Existing `coreseogeo_404_hits`/`coreseogeo_redirect_hits` rows are carried over losslessly
 * (they're already bucketed the same way, just missing a `type`/`entityId`). The "new 404"/
 * "new redirect" counts aren't backfilled from `coreseogeo_404s`/`coreseogeo_redirects`'
 * `dateCreated` columns - those charts will simply show a cold-start gap for the 30 days
 * after this migration runs, the same unavoidable trade-off any brand new metric type has.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.20.0
 */
class m261026_100000_add_metrics_table extends Migration {
    /**
     * `RedirectsService::METRIC_TYPE_NOTFOUND_HIT`/`METRIC_TYPE_REDIRECT_HIT` - duplicated
     * here rather than referenced, since a migration should stay self-contained and not
     * depend on application service code that's free to change after this migration has
     * already run against someone's database.
     *
     * @since v1.20.0
     */
    private const string METRIC_TYPE_NOTFOUND_HIT = 'notfound_hit';
    private const string METRIC_TYPE_REDIRECT_HIT = 'redirect_hit';

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    public function safeUp(): bool {
        if (!$this->db->tableExists(Table::METRICS)) {
            $this->createTable(Table::METRICS, [
                'id' => $this->primaryKey(),
                'type' => $this->string()->notNull(),
                'entityId' => $this->integer()->notNull()->defaultValue(0),
                'siteId' => $this->integer()->notNull(),
                'timestamp' => $this->dateTime()->notNull(),
                'hitCount' => $this->integer()->notNull()->defaultValue(1),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, Table::METRICS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
            $this->createIndex(null, Table::METRICS, ['type', 'entityId', 'siteId', 'timestamp'], true);
        }

        if ($this->db->tableExists(Table::NOTFOUNDHITS)) {
            $this->_copyHitRowsToMetrics(Table::NOTFOUNDHITS, self::METRIC_TYPE_NOTFOUND_HIT);
            $this->dropTableIfExists(Table::NOTFOUNDHITS);
        }

        if ($this->db->tableExists(Table::REDIRECTHITS)) {
            $this->_copyHitRowsToMetrics(Table::REDIRECTHITS, self::METRIC_TYPE_REDIRECT_HIT);
            $this->dropTableIfExists(Table::REDIRECTHITS);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    public function safeDown(): bool {
        $this->createTable(Table::NOTFOUNDHITS, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'timestamp' => $this->dateTime()->notNull(),
            'hitCount' => $this->integer()->notNull()->defaultValue(1),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::NOTFOUNDHITS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::NOTFOUNDHITS, ['siteId', 'timestamp'], true);

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

        if ($this->db->tableExists(Table::METRICS)) {
            $this->_copyMetricsRowsToHitTable(self::METRIC_TYPE_NOTFOUND_HIT, Table::NOTFOUNDHITS);
            $this->_copyMetricsRowsToHitTable(self::METRIC_TYPE_REDIRECT_HIT, Table::REDIRECTHITS);

            $this->dropTableIfExists(Table::METRICS);
        }

        return true;
    }

    /**
     * Copies every row of a `(siteId, timestamp, hitCount, ...)`-shaped hit-bucket table
     * (the old `coreseogeo_404_hits`/`coreseogeo_redirect_hits` shape) into `coreseogeo_metrics`
     * as `$type` rows at `entityId = 0` - a straight row-for-row copy, since both old tables
     * are already bucketed exactly the way `coreseogeo_metrics` is.
     *
     * @param string $fromTable
     * @param string $type
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    private function _copyHitRowsToMetrics(string $fromTable, string $type): void {
        foreach ((new Query())->from($fromTable)->each() as $row) {
            $this->db->createCommand()->insert(Table::METRICS, [
                'type' => $type,
                'entityId' => 0,
                'siteId' => $row['siteId'],
                'timestamp' => $row['timestamp'],
                'hitCount' => $row['hitCount'],
                'dateCreated' => $row['dateCreated'],
                'dateUpdated' => $row['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ])->execute();
        }
    }

    /**
     * `_copyHitRowsToMetrics()`'s exact reverse, for `safeDown()`: copies every `coreseogeo_
     * metrics` row of `$type` into `$toTable`'s `(siteId, timestamp, hitCount, ...)` shape,
     * summing across every `entityId` into one row per `(siteId, timestamp)` - `redirect_hit`
     * rows carry a real `entityId` today (the redirect that was hit), which the old table's
     * shape has no column for.
     *
     * @param string $type
     * @param string $toTable
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    private function _copyMetricsRowsToHitTable(string $type, string $toTable): void {
        $summed = (new Query())
            ->select(['siteId', 'timestamp', 'hitCount' => 'SUM(hitCount)', 'dateCreated' => 'MIN(dateCreated)', 'dateUpdated' => 'MAX(dateUpdated)'])
            ->from(Table::METRICS)
            ->where(['type' => $type])
            ->groupBy(['siteId', 'timestamp'])
            ->all();

        foreach ($summed as $row) {
            $this->db->createCommand()->insert($toTable, [
                'siteId' => $row['siteId'],
                'timestamp' => $row['timestamp'],
                'hitCount' => $row['hitCount'],
                'dateCreated' => $row['dateCreated'],
                'dateUpdated' => $row['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ])->execute();
        }
    }
}
