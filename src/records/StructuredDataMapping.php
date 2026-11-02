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
use craft\elements\Entry;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class StructuredDataMapping
 *
 * Active record for the `coreseogeo_structured_data_mappings` table: the schema.org type/property
 * mapping configuration for an entry type - scoped by entry type rather than section so a mapping
 * can target an entry type used inside a Matrix field (which has no section of its own) just as
 * well as one used directly in a section. No `siteId` - a mapping's property templates resolve
 * per element (and so, through the element, per site) the same way a `SeoField`'s templates do,
 * so the configuration itself isn't site-specific. Unlike `ContentSeo`, more than one row can
 * exist per `entryTypeHandle` - an entry type can have several mappings (e.g. a `Product` one and
 * a `BreadcrumbList` one).
 *
 * @property int $id
 * @property string $entryTypeHandle
 * @property string|null $label
 * @property string $type a schema.org type name, e.g. `Product`
 * @property string|null $properties JSON-encoded, decode with `craft\helpers\Json::decode()`
 * @property int|null $previewElementId the entry chosen in the mapping editor's "Preview Entry"
 * field, to calculate each property's value against - mirrors `StructuredDataField::$previewElementId`
 * @property bool $enabled whether this mapping still computes/outputs structured data - lets it
 * be turned off without deleting it and its already-configured properties
 * @property int|null $sortOrder
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.3.0
 */
class StructuredDataMapping extends ActiveRecord {
    /**
     * @inheritdoc
     *
     * Seeds `enabled` with its table default for a brand new record, the same value the
     * database column would fall back to anyway - without this, a freshly-`new`'d instance
     * leaves it `null` until saved, which would show the mapping editor's lightswitch off for a
     * mapping that hasn't even been saved yet.
     *
     * @author      Digitalastronaut
     * @since       v1.39.0
     */
    public function init(): void {
        parent::init();

        if ($this->getIsNewRecord()) {
            $this->enabled = true;
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public static function tableName(): string {
        return Table::STRUCTUREDDATAMAPPINGS;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function rules(): array {
        return array_merge(parent::rules(), [
            [['entryTypeHandle', 'type'], 'required'],
            [['enabled'], 'boolean'],
            [['type'], function(string $attribute): void {
                if (CoreSeoGeo::getInstance()->getSchemaOrg()->getType($this->$attribute) === null) {
                    $this->addError($attribute, Craft::t('core-seo-geo', '{value} isn\'t a known schema.org type.', [
                        'value' => $this->$attribute,
                    ]));
                }
            }],
        ]);
    }

    /**
     * The entry chosen as this mapping's "Preview Entry", if any - the same preview mechanism
     * `StructuredDataField::getPreviewEntry()` provides for a field's own settings.
     *
     * @return Entry|null
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function getPreviewEntry(): ?Entry {
        if ($this->previewElementId === null) return null;

        /** @var Entry|null $entry */
        $entry = Craft::$app->getElements()->getElementById((int)$this->previewElementId, Entry::class);

        return $entry;
    }
}
