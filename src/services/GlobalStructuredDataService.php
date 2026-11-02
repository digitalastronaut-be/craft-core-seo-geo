<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\services;

use Craft;
use craft\base\Component;
use craft\base\Model;

use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\models\structureddata\LocalBusinessData;
use digitalastronaut\craftcoreseogeo\models\structureddata\OrganizationData;
use digitalastronaut\craftcoreseogeo\models\structureddata\PersonData;
use digitalastronaut\craftcoreseogeo\models\structureddata\StructuredDataFormModel;
use digitalastronaut\craftcoreseogeo\models\structureddata\WebsiteData;

/**
 * Class GlobalStructuredDataService
 *
 * Reads and writes the per-site, standalone `StructuredData` elements (`mappingId`/`ownerId`
 * both null) behind the plugin's built-in Organization/WebSite/LocalBusiness/Person settings
 * pages. Unlike a section's structured data mappings, there's no user-facing Twig template step
 * here - each type's settings-page model ({@see OrganizationData} and friends) *is* the fixed
 * mapping, via its own `toProperties()`.
 *
 * A site "has" one of these objects (and so emits it on every page) exactly when a saved,
 * enabled element for that `(type, siteId)` exists - there's no separate opt-in flag to keep in
 * sync with that.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class GlobalStructuredDataService extends Component {
    /**
     * @var array<string, string> URL slug => `StructuredData::TYPE_*` constant, for this
     * service's CP routes (`core-seo-geo/settings/global/<slug>`).
     *
     * @since v1.5.0
     */
    public const array TYPE_SLUGS = [
        'organization' => StructuredDataElement::TYPE_ORGANIZATION,
        'website' => StructuredDataElement::TYPE_WEBSITE,
        'local-business' => StructuredDataElement::TYPE_LOCAL_BUSINESS,
        'person' => StructuredDataElement::TYPE_PERSON,
    ];

    /**
     * @var array<string, class-string<StructuredDataFormModel>> `StructuredData::TYPE_*`
     * constant => the model class whose `toProperties()` is that type's fixed mapping.
     *
     * @since v1.5.0
     */
    private const array TYPE_MODELS = [
        StructuredDataElement::TYPE_ORGANIZATION => OrganizationData::class,
        StructuredDataElement::TYPE_WEBSITE => WebsiteData::class,
        StructuredDataElement::TYPE_LOCAL_BUSINESS => LocalBusinessData::class,
        StructuredDataElement::TYPE_PERSON => PersonData::class,
    ];

    /**
     * @var array<string, string> `StructuredData::TYPE_*` constant => its settings page's title.
     *
     * @since v1.5.0
     */
    private const array TYPE_LABELS = [
        StructuredDataElement::TYPE_ORGANIZATION => 'Organization',
        StructuredDataElement::TYPE_WEBSITE => 'Website',
        StructuredDataElement::TYPE_LOCAL_BUSINESS => 'Local Business',
        StructuredDataElement::TYPE_PERSON => 'Person',
    ];

    /**
     * @param string $slug
     * @return string|null the matching `StructuredData::TYPE_*` constant, or `null` if `$slug`
     * isn't one of `TYPE_SLUGS`
     *
     * @since v1.5.0
     */
    public function getTypeForSlug(string $slug): ?string {
        return self::TYPE_SLUGS[$slug] ?? null;
    }

    /**
     * @param string $type a `StructuredData::TYPE_*` constant
     * @return string
     *
     * @since v1.5.0
     */
    public function getTypeLabel(string $type): string {
        return Craft::t('core-seo-geo', self::TYPE_LABELS[$type] ?? $type);
    }

    /**
     * Finds this site's standalone element for the given built-in type, if it's been configured
     * yet.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @return StructuredDataElement|null
     *
     * @since v1.5.0
     */
    public function getElement(string $type, int $siteId): ?StructuredDataElement {
        return StructuredDataElement::find()
            ->type($type)
            ->mappingId(':empty:')
            ->ownerId(':empty:')
            ->siteId($siteId)
            ->status(null)
            ->one();
    }

    /**
     * The existing element for `(type, siteId)` ({@see getElement()}), or an unsaved one ready
     * to be filled in - the same "find it, or start a new one" `getElement()`'s callers would
     * otherwise all repeat themselves.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @return StructuredDataElement
     *
     * @since v1.5.0
     */
    public function getOrNewElement(string $type, int $siteId): StructuredDataElement {
        return $this->getElement($type, $siteId) ?? new StructuredDataElement([
            'type' => $type,
            'siteId' => $siteId,
        ]);
    }

    /**
     * Builds the settings-page model for the given built-in type and site - populated from the
     * existing element's `sourceData` when one's already been configured, or a fresh, empty
     * model (with `url` defaulted to the site's base URL, for `Organization`/`WebSite`) when
     * nothing's been saved yet.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @return Model&StructuredDataFormModel one of `TYPE_MODELS`
     *
     * @since v1.5.0
     */
    public function getModel(string $type, int $siteId): Model & StructuredDataFormModel {
        $class = self::TYPE_MODELS[$type] ?? throw new \InvalidArgumentException("Unknown structured data type \"{$type}\".");
        $model = new $class();
        $element = $this->getElement($type, $siteId);

        if ($element !== null && $element->sourceData !== []) {
            Craft::configure($model, $element->sourceData);
        } else {
            $site = Craft::$app->getSites()->getSiteById($siteId);

            if (property_exists($model, 'url')) {
                $model->url = $site?->getBaseUrl();
            }

            if (property_exists($model, 'inLanguage')) {
                $model->inLanguage = $site?->getLanguage();
            }
        }

        return $model;
    }

    /**
     * Persists the given model as this site's standalone element for `$type`, computing
     * `properties` from it and keeping its posted attributes around as `sourceData` so the
     * settings page can be repopulated later. Creates the element when none exists yet for this
     * `(type, siteId)`.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @param Model&StructuredDataFormModel $model one of `TYPE_MODELS`
     * @param bool $enabled whether the type's settings-page lightswitch was on - lets a site keep
     * its filled-in fields around while skipping the type entirely, rather than forcing fields to
     * be emptied out to turn it off
     * @return bool whether the save succeeded
     *
     * @since v1.5.0
     */
    public function save(string $type, int $siteId, Model & StructuredDataFormModel $model, bool $enabled): bool {
        $element = $this->getOrNewElement($type, $siteId);

        $element->enabled = $enabled;
        $element->title = $this->getTypeLabel($type);
        $element->sourceData = $model->getAttributes();
        $element->properties = $model->toProperties();

        return Craft::$app->getElements()->saveElement($element);
    }
}
