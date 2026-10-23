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
 * Class SeoScore
 *
 * Active record for the `coreseogeo_seoscores` table: one row per element+site+field, holding
 * the last computed SEO score and whether a recalculation is currently pending. Thin by design,
 * no business logic here -- `SeoScoreService` owns the scoring logic.
 *
 * @property int $id
 * @property int $elementId
 * @property int $siteId
 * @property int $fieldId
 * @property int|null $score
 * @property bool $pending
 * @property string|null $metaTitle
 * @property string|null $metaDescription
 * @property string|null $checks
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class SeoScore extends ActiveRecord {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public static function tableName(): string {
        return Table::SEOSCORE;
    }
}
