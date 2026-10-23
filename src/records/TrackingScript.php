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
 * Class TrackingScript
 *
 * Active record for the `coreseogeo_trackingscripts` table: one user-added tracking script, e.g.
 * a Google Tag Manager or Meta Pixel snippet. Each row carries two independent templates - a head
 * script and its paired body script (typically a `<noscript>` fallback) - each with its own
 * injection point (`scriptLocation`/`bodyScriptLocation`), since the two halves of a tracking
 * snippet are conventionally placed in different parts of the page.
 *
 * @property int $id
 * @property string $name
 * @property bool $enabled
 * @property string|null $scriptTemplate
 * @property string $scriptLocation one of the `LOCATION_*` constants
 * @property string|null $bodyScriptTemplate
 * @property string $bodyScriptLocation one of the `LOCATION_*` constants
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.9
 */
class TrackingScript extends ActiveRecord {
    /**
     * @since v1.0.9
     */
    public const string LOCATION_HEAD_START = 'headStart';
    public const string LOCATION_HEAD_END = 'headEnd';
    public const string LOCATION_BODY_START = 'bodyStart';
    public const string LOCATION_BODY_END = 'bodyEnd';

    /**
     * @since v1.0.9
     */
    public const array LOCATIONS = [
        self::LOCATION_HEAD_START,
        self::LOCATION_HEAD_END,
        self::LOCATION_BODY_START,
        self::LOCATION_BODY_END,
    ];

    /**
     * @inheritdoc
     *
     * Seeds `scriptLocation`/`bodyScriptLocation` with their table defaults for a brand new
     * record, the same values the database column would fall back to anyway - without this, a
     * freshly-`new`'d instance leaves both `null` until saved, which would show the edit form's
     * selectize fields with nothing selected.
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function init(): void {
        parent::init();

        if ($this->getIsNewRecord()) {
            $this->enabled = true;
            $this->scriptLocation = self::LOCATION_HEAD_END;
            $this->bodyScriptLocation = self::LOCATION_BODY_START;
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public static function tableName(): string {
        return Table::TRACKINGSCRIPTS;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function rules(): array {
        return array_merge(parent::rules(), [
            [['name'], 'required'],
            [['scriptLocation', 'bodyScriptLocation'], 'required'],
            [['scriptLocation', 'bodyScriptLocation'], 'in', 'range' => self::LOCATIONS],
            [['enabled'], 'boolean'],
        ]);
    }

    /**
     * The `scriptLocation`/`bodyScriptLocation` selectize fields' options, shared between both
     * since either template can be placed at any of the four points.
     *
     * @return array<int, array{value: string, label: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public static function getLocationOptions(): array {
        return [
            ['value' => self::LOCATION_HEAD_START, 'label' => Craft::t('core-seo-geo', 'Start of head')],
            ['value' => self::LOCATION_HEAD_END, 'label' => Craft::t('core-seo-geo', 'End of head')],
            ['value' => self::LOCATION_BODY_START, 'label' => Craft::t('core-seo-geo', 'Start of body')],
            ['value' => self::LOCATION_BODY_END, 'label' => Craft::t('core-seo-geo', 'End of body')],
        ];
    }
}
