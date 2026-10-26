<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\records;

use craft\db\ActiveRecord;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class Metric
 *
 * Active record for the `coreseogeo_metrics` table: one row per 5-minute bucket per
 * `(type, entityId, siteId)` combination, with a running `hitCount` - `RedirectsService::
 * METRIC_TYPE_*`'s own storage, replacing what used to be two separate tables
 * (`coreseogeo_404_hits`/`coreseogeo_redirect_hits`) and two `dateCreated` group-bys. A single
 * generic table rather than one per metric: every metric here is counted the exact same way
 * (an atomic upsert-increment on a time bucket), so a new metric type is just a new `type`
 * string, not a new migration.
 *
 * `entityId` is `0`, never `null`, when a metric isn't about one specific entity (e.g. a
 * sitewide "new 404" count) - a nullable column would break the unique index's upsert
 * dedup, since MySQL treats every `NULL` in a unique index as distinct from every other one.
 * It carries no foreign key, since which table (if any) it points into depends on `type`.
 *
 * @property int $id
 * @property string $type one of `RedirectsService::METRIC_TYPE_*`
 * @property int $entityId `0` when this metric isn't about one specific entity
 * @property int $siteId
 * @property string $timestamp the bucket's start, truncated down to a 5-minute boundary
 * @property int $hitCount
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.20.0
 */
class Metric extends ActiveRecord {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    public function init(): void {
        parent::init();

        if ($this->getIsNewRecord()) {
            $this->entityId = 0;
            $this->hitCount = 1;
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    public static function tableName(): string {
        return Table::METRICS;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    public function rules(): array {
        return array_merge(parent::rules(), [
            [['type', 'siteId', 'timestamp'], 'required'],
        ]);
    }
}
