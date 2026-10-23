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
 * Class NotFoundLogEntry
 *
 * Active record for the `coreseogeo_404s` table: one row per distinct 404'd URI that
 * found no matching `Redirect` (see `redirects-roadmap.md` Step 1). A URI that *does*
 * match a redirect never reaches this table at all - its hit is counted on the
 * `Redirect` row instead, since it's no longer a gap that needs fixing.
 *
 * @property int $id
 * @property int $siteId
 * @property string $uri normalized path (no leading slash, no query string)
 * @property string|null $referrer last-seen `Referer` header, if any
 * @property int $hitCount
 * @property string $dateLastHit
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
class NotFoundLogEntry extends ActiveRecord {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function init(): void {
        parent::init();

        if ($this->getIsNewRecord()) {
            $this->hitCount = 1;
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public static function tableName(): string {
        return Table::NOTFOUNDLOG;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function rules(): array {
        return array_merge(parent::rules(), [
            [['siteId', 'uri'], 'required'],
        ]);
    }
}
