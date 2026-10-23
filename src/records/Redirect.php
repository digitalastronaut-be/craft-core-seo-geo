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

use Craft;
use craft\db\ActiveRecord;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class Redirect
 *
 * Active record for the `coreseogeo_redirects` table: one registered redirect, either
 * manually added or auto-created from an entry's URL changing (`sourceElementId` set in
 * that case). `matchType` distinguishes an exact-string row from a regex one on the same
 * table, rather than splitting into two tables the way `nystudio107/craft-retour` does.
 *
 * @property int $id
 * @property int|null $siteId `null` applies to every site
 * @property string $matchType one of the `MATCH_TYPE_*` constants
 * @property string $sourceUri normalized path (no leading slash, no query string) when
 *                              `matchType` is exact; a regex pattern otherwise
 * @property string|null $destinationUri relative path or absolute URL; `null` only when
 *                                        `statusCode` is `STATUS_CODE_GONE`
 * @property int $statusCode one of the `STATUS_CODE_*` constants
 * @property bool $enabled
 * @property int|null $sourceElementId the entry that triggered this redirect, if auto-created
 * @property int $hitCount
 * @property string|null $dateLastHit
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
class Redirect extends ActiveRecord {
    /**
     * @since v1.16.0
     */
    public const string MATCH_TYPE_EXACT = 'exact';
    public const string MATCH_TYPE_REGEX = 'regex';

    /**
     * @since v1.16.0
     */
    public const array MATCH_TYPES = [
        self::MATCH_TYPE_EXACT,
        self::MATCH_TYPE_REGEX,
    ];

    /**
     * @since v1.16.0
     */
    public const int STATUS_CODE_MOVED_PERMANENTLY = 301;
    public const int STATUS_CODE_FOUND = 302;
    public const int STATUS_CODE_GONE = 410;

    /**
     * @since v1.16.0
     */
    public const array STATUS_CODES = [
        self::STATUS_CODE_MOVED_PERMANENTLY,
        self::STATUS_CODE_FOUND,
        self::STATUS_CODE_GONE,
    ];

    /**
     * @inheritdoc
     *
     * Seeds `matchType`/`statusCode`/`enabled` with their table defaults for a brand new
     * record, the same values the database column would fall back to anyway - without this, a
     * freshly-`new`'d instance leaves them `null` until saved, which would show the edit form's
     * select fields with nothing selected.
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function init(): void {
        parent::init();

        if ($this->getIsNewRecord()) {
            $this->matchType = self::MATCH_TYPE_EXACT;
            $this->statusCode = self::STATUS_CODE_MOVED_PERMANENTLY;
            $this->enabled = true;
            $this->hitCount = 0;
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public static function tableName(): string {
        return Table::REDIRECTS;
    }

    /**
     * @inheritdoc
     *
     * `destinationUri` is required unless `statusCode` is `STATUS_CODE_GONE` - a 410 response
     * deliberately has nowhere to send the visitor.
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function rules(): array {
        return array_merge(parent::rules(), [
            [['sourceUri'], 'required'],
            [['matchType'], 'required'],
            [['matchType'], 'in', 'range' => self::MATCH_TYPES],
            [['statusCode'], 'required'],
            [['statusCode'], 'in', 'range' => self::STATUS_CODES],
            [['destinationUri'], 'required', 'when' => fn(self $model) => (int)$model->statusCode !== self::STATUS_CODE_GONE],
            [['enabled'], 'boolean'],
        ]);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public static function getMatchTypeOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', 'Exact'), 'value' => self::MATCH_TYPE_EXACT],
            ['label' => Craft::t('core-seo-geo', 'Regex'), 'value' => self::MATCH_TYPE_REGEX],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int}>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public static function getStatusCodeOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', '301 (Moved Permanently)'), 'value' => self::STATUS_CODE_MOVED_PERMANENTLY],
            ['label' => Craft::t('core-seo-geo', '302 (Found)'), 'value' => self::STATUS_CODE_FOUND],
            ['label' => Craft::t('core-seo-geo', '410 (Gone)'), 'value' => self::STATUS_CODE_GONE],
        ];
    }
}
