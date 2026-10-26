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
 * @property bool $resolved manually marked fixed from the detail page - `RedirectsService::
 * _recordNotFound()` flips this back to `false` the next time this same `(siteId, uri)` 404s
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
            $this->resolved = false;
        }
    }

    /**
     * Whether this URI looks like an asset request rather than a page request, judged
     * purely by whether its last path segment has a file extension - a page's `uri` in
     * Craft is never extension-less, an asset's filename always is. No DB lookup against
     * actual volumes: this is a display hint for the "404s" tab (`redirects-roadmap.md`
     * Step 4), not a guarantee the URI ever matched a real asset.
     *
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getIsAsset(): bool {
        return pathinfo($this->uri, PATHINFO_EXTENSION) !== '';
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
            [['resolved'], 'boolean'],
        ]);
    }
}
