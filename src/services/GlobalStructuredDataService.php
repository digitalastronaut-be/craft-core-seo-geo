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
use craft\elements\Address;
use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;

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
 * `Organization` and `LocalBusiness` each get their own real, native `craft\fields\Addresses`
 * field (`ADDRESS_FIELD_HANDLES`) - but neither field lives on `StructuredData`'s own field
 * layout. Both live on one shared "Business Addresses" global set instead (created by
 * `m261024_100000_add_business_address_global_set`), so an address never clutters a
 * `coreseogeo_structureddata` row and never shows up on a `StructuredData` element's own edit
 * screen - this service just points `CustomField` at whichever of the two fields the current
 * type needs, against that global set rather than against the `StructuredData` element.
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
     * @var string The handle of the global set `m261024_100000_add_business_address_global_set`
     * creates to hold `ADDRESS_FIELD_HANDLES`' two fields. Literal here (and in that migration)
     * rather than one shared constant - a migration shouldn't depend on application code that
     * might later change out from under it.
     *
     * @since v1.5.0
     */
    private const string ADDRESS_GLOBAL_SET_HANDLE = 'coreSeoGeoBusinessAddresses';

    /**
     * @var array<string, string> `StructuredData::TYPE_*` constant => the handle of that type's
     * own `craft\fields\Addresses` field on the `ADDRESS_GLOBAL_SET_HANDLE` global set. Only
     * `Organization`/`LocalBusiness` have one - see `StructuredData::typeHasAddress()`.
     *
     * @since v1.5.0
     */
    private const array ADDRESS_FIELD_HANDLES = [
        StructuredDataElement::TYPE_ORGANIZATION => 'organizationAddress',
        StructuredDataElement::TYPE_LOCAL_BUSINESS => 'localBusinessAddress',
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
    public function getModel(string $type, int $siteId): Model&StructuredDataFormModel {
        $class = self::TYPE_MODELS[$type] ?? throw new \InvalidArgumentException("Unknown structured data type \"{$type}\".");
        $model = new $class();
        $element = $this->getElement($type, $siteId);

        if ($element !== null && $element->sourceData !== []) {
            Craft::configure($model, $element->sourceData);
        } elseif (property_exists($model, 'url')) {
            $model->url = Craft::$app->getSites()->getSiteById($siteId)?->getBaseUrl();
        }

        return $model;
    }

    /**
     * The shared "Business Addresses" global set for the given site -
     * `m261024_100000_add_business_address_global_set` creates it, so `null` here means that
     * migration hasn't run yet.
     *
     * @param int $siteId
     * @return GlobalSet|null
     *
     * @since v1.5.0
     */
    public function getAddressGlobalSet(int $siteId): ?GlobalSet {
        return Craft::$app->getGlobals()->getSetByHandle(self::ADDRESS_GLOBAL_SET_HANDLE, $siteId);
    }

    /**
     * Finds the given type's saved `Address`, if it has one yet.
     *
     * @param string $type a `StructuredData::TYPE_*` constant with an entry in
     * `ADDRESS_FIELD_HANDLES`
     * @param int $siteId
     * @return Address|null
     *
     * @since v1.5.0
     */
    public function getSavedAddress(string $type, int $siteId): ?Address {
        $globalSet = $this->getAddressGlobalSet($siteId);
        $field = Craft::$app->getFields()->getFieldByHandle(self::ADDRESS_FIELD_HANDLES[$type] ?? '');

        if ($globalSet === null || $field === null) return null;

        return Address::find()->owner($globalSet)->fieldId($field->id)->one();
    }

    /**
     * Renders the given type's own `craft\fields\Addresses` field - Craft's native,
     * country-aware address input, exactly as it'd appear on any other element's edit screen -
     * against the shared "Business Addresses" global set, for splicing into
     * `global-organization.twig`/`global-local-business.twig`. Already namespaced under `fields`
     * by `CustomField::formHtml()` itself, so it posts back as
     * `fields[organizationAddress][...]`/`fields[localBusinessAddress][...]`, which `save()`
     * reads via `setFieldValuesFromRequest()`. `null` for a type with no address at all
     * (`WebSite`/`Person`), or if the migration that creates the global set/fields hasn't run.
     *
     * `width: 50` matches the `fieldClass: 'width-50'` those templates give the "Social
     * Profiles" field next to it - the same `FieldLayoutElement::$width` mechanism Craft's own
     * field layouts use for a half-width field, just set directly rather than through a
     * designer, so the two sit side by side in a shared `.flex-fields` container.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @return string|null
     *
     * @since v1.5.0
     */
    public function getAddressFieldHtml(string $type, int $siteId): ?string {
        if (!StructuredDataElement::typeHasAddress($type)) return null;

        $globalSet = $this->getAddressGlobalSet($siteId);
        $field = Craft::$app->getFields()->getFieldByHandle(self::ADDRESS_FIELD_HANDLES[$type] ?? '');

        if ($globalSet === null || $field === null) return null;

        return (new CustomField($field, ['width' => 50]))->formHtml($globalSet, false);
    }

    /**
     * Persists the given model as this site's standalone element for `$type`, computing
     * `properties` from it and keeping its posted attributes around as `sourceData` so the
     * settings page can be repopulated later. Creates the element when none exists yet for this
     * `(type, siteId)`.
     *
     * For `Organization`/`LocalBusiness`, also pulls the posted `fields[<handle>]` data (from
     * `getAddressFieldHtml()`'s input) onto the shared "Business Addresses" global set and, once
     * Craft's own `NestedElementManager` has persisted it, reads it back to fold `address`/`geo`
     * into `properties`.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @param Model&StructuredDataFormModel $model one of `TYPE_MODELS`
     * @return bool whether the save succeeded
     *
     * @since v1.5.0
     */
    public function save(string $type, int $siteId, Model&StructuredDataFormModel $model): bool {
        $element = $this->getOrNewElement($type, $siteId);

        $element->title = $this->getTypeLabel($type);
        $element->sourceData = $model->getAttributes();
        $element->properties = $model->toProperties();

        if (!Craft::$app->getElements()->saveElement($element)) return false;

        if (StructuredDataElement::typeHasAddress($type)) {
            $globalSet = $this->getAddressGlobalSet($siteId);

            if ($globalSet !== null) {
                $globalSet->setFieldValuesFromRequest('fields');

                if (!Craft::$app->getElements()->saveElement($globalSet)) return false;

                $element->properties = array_merge($element->properties, $this->_addressProperties($type, $siteId));

                if (!Craft::$app->getElements()->saveElement($element)) return false;
            }
        }

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds `address` (and, for a `LocalBusiness`, `geo`) from the given type's saved
     * `Address`. `[]` when there's no address to report - the same "no value, no property" rule
     * the rest of this object's properties follow.
     *
     * @param string $type a `StructuredData::TYPE_*` constant
     * @param int $siteId
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    private function _addressProperties(string $type, int $siteId): array {
        $address = $this->getSavedAddress($type, $siteId);

        if ($address === null) return [];

        $street = trim(implode(' ', array_filter([$address->addressLine1, $address->addressLine2])));

        $data = array_filter([
            'streetAddress' => $street !== '' ? $street : null,
            'addressLocality' => $address->locality,
            'addressRegion' => $address->administrativeArea,
            'postalCode' => $address->postalCode,
            'addressCountry' => $address->countryCode,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        $properties = [];

        if ($data !== []) {
            $properties['address'] = array_merge(['@type' => 'PostalAddress'], $data);
        }

        if ($type === StructuredDataElement::TYPE_LOCAL_BUSINESS && $address->latitude !== null && $address->longitude !== null) {
            $properties['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ];
        }

        return $properties;
    }
}
