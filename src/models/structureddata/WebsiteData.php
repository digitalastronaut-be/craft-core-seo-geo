<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models\structureddata;

use Craft;
use craft\base\Model;
use craft\helpers\Json;

use yii\base\InvalidArgumentException;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class WebsiteData
 *
 * The settings-page form model behind the global `WebSite` structured data object - see
 * {@see \digitalastronaut\craftcoreseogeo\models\structureddata\OrganizationData}'s class
 * docblock for how this fits into `GlobalStructuredDataService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class WebsiteData extends Model implements StructuredDataFormModel {
    public ?string $name = null;
    public ?string $url = null;

    /**
     * @var string|null schema.org `WebSite.description` - a short description of the site.
     *
     * @since v1.5.0
     */
    public ?string $description = null;

    /**
     * @var string|null schema.org `WebSite.inLanguage` - the site's primary language, as a
     * BCP 47 tag (e.g. `en-US`). Defaulted from the current site's own language by
     * `GlobalStructuredDataService::getModel()`, the same way `$url` is defaulted from its base
     * URL.
     *
     * @since v1.5.0
     */
    public ?string $inLanguage = null;

    /**
     * @var string|null Raw JSON object merged into `toProperties()`'s result last, overriding
     * any matching key - see `OrganizationData::$additionalProperties`.
     *
     * @since v1.5.0
     */
    public ?string $additionalProperties = null;

    /**
     * @inheritdoc
     * @return array
     *
     * @since v1.5.0
     */
    public function defineRules(): array {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 1000],
            [['inLanguage'], 'string', 'max' => 35],
            [['url'], 'url', 'defaultScheme' => 'https'],
            [['additionalProperties'], function(string $attribute): void {
                if ($this->$attribute === null || $this->$attribute === '') return;

                try {
                    $decoded = Json::decode($this->$attribute);
                } catch (InvalidArgumentException) {
                    $decoded = null;
                }

                if (!\is_array($decoded) || array_is_list($decoded)) {
                    $this->addError($attribute, Craft::t('core-seo-geo', '{attribute} must be a valid JSON object.', [
                        'attribute' => Craft::t('core-seo-geo', 'Additional Properties'),
                    ]));
                }
            }],
        ];
    }

    /**
     * Builds the `WebSite` schema.org properties this object's fields map to, via
     * `spatie/schema-org`'s fluent builder rather than hand-assembling the array - its setters
     * already drop `null`/`''` values on their own, the same "no value, no property" rule
     * `OrganizationData::toProperties()` follows.
     *
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    public function toProperties(): array {
        $website = CoreSeoGeo::getInstance()->getSchemaOrg()->build('WebSite')
            ->name($this->name)
            ->url($this->url)
            ->description($this->description)
            ->inLanguage($this->inLanguage);

        $properties = $website->toArray();
        unset($properties['@context'], $properties['@type']);

        return array_merge($properties, $this->additionalPropertiesData());
    }

    /**
     * Decodes `$additionalProperties` for `toProperties()`'s final merge - see
     * `OrganizationData::additionalPropertiesData()`.
     *
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    protected function additionalPropertiesData(): array {
        if ($this->additionalProperties === null || $this->additionalProperties === '') {
            return [];
        }

        try {
            $decoded = Json::decode($this->additionalProperties);
        } catch (InvalidArgumentException) {
            return [];
        }

        return \is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }
}
