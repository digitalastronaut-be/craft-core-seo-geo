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
 * Active record for the `coreseogeo_structureddatamappings` table: the schema.org type/property
 * mapping configuration for a section (plus any site-wide ones, via an empty `sectionHandle`).
 * No `siteId` - a mapping's property templates resolve per element (and so, through the
 * element, per site) the same way a `SeoField`'s templates do, so the configuration itself
 * isn't site-specific. Unlike `ContentSeo`, more than one row can exist per `sectionHandle` - a
 * section can have several mappings (e.g. a `Product` one and a `BreadcrumbList` one).
 *
 * @property int $id
 * @property string $sectionHandle empty string for a site-wide mapping, a section's handle otherwise
 * @property string|null $label
 * @property string $type a schema.org type name, e.g. `Product`
 * @property string|null $properties JSON-encoded, decode with `craft\helpers\Json::decode()`
 * @property int|null $previewElementId the entry chosen in the mapping editor's "Preview Entry"
 * field, to calculate each property's value against - mirrors `StructuredDataField::$previewElementId`
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
            [['sectionHandle', 'type'], 'required'],
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
