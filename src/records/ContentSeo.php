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
use craft\elements\Asset;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class ContentSeo
 *
 * Active record for the `coreseogeo_seodata` table: one row per section+site, plus one row per
 * site with an empty `sectionHandle` (the global fallback bundle). Holds the title/description
 * block config, robots directives, canonical config, and fallback image that used to live on
 * `SeoField`'s own field settings. `ContentSeoService` owns the sync/query logic; this is a thin
 * data-access wrapper, same as `SeoScore`.
 *
 * @property int $id
 * @property int $siteId
 * @property string $sectionHandle empty string for the global bundle row, a section's handle otherwise
 * @property string|null $sectionLabel null for the global bundle row
 * @property string|null $titleConfig JSON-encoded, decode with `craft\helpers\Json::decode()`
 * @property string|null $descriptionConfig JSON-encoded, decode with `craft\helpers\Json::decode()`
 * @property string|null $robots JSON-encoded, decode with `craft\helpers\Json::decode()`
 * @property string|null $canonicalConfig JSON-encoded, decode with `craft\helpers\Json::decode()`
 * @property int|null $imageId the fallback social image a `SeoField`'s own per-entry image falls
 * back to when it's left empty - see `getImageAsset()`
 * @property bool $sitemapEnabled whether this section (or the global bundle) is included in the
 * sitemap - its index entry and its own sub-sitemap both skip it when `false`
 * @property string|null $llmsTxtTitle the global bundle row's `llms.txt` title for this site -
 * `null` elsewhere, see `LlmsService::getLlmsTxt()`
 * @property string|null $llmsTxtSummary the global bundle row's `llms.txt` summary for this site
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class ContentSeo extends ActiveRecord {
    /**
     * Whether this row is the global fallback bundle rather than a section's own row.
     *
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getIsGlobal(): bool {
        return $this->sectionHandle === '';
    }

    /**
     * The asset this row's `imageId` points to, if any.
     *
     * @return Asset|null
     *
     * @author      Digitalastronaut
     * @since       v1.9.0
     */
    public function getImageAsset(): ?Asset {
        return $this->imageId !== null ? Asset::find()->id((int)$this->imageId)->one() : null;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public static function tableName(): string {
        return Table::SEODATA;
    }
}
