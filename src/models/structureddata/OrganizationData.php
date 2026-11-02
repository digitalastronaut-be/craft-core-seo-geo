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
use craft\elements\Asset;
use craft\helpers\Json;

use yii\base\InvalidArgumentException;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class OrganizationData
 *
 * The settings-page form model behind the global `Organization` structured data object -
 * {@see \digitalastronaut\craftcoreseogeo\services\GlobalStructuredDataService} builds one of
 * these from a standalone `StructuredData` element's `sourceData` (or from nothing, for a site
 * that hasn't configured one yet), `global-organization.twig` renders its fields, and
 * `toProperties()` is the fixed, code-only mapping from those fields to the `Organization`
 * schema.org shape - there's no user-facing template/mapping step the way a section's own
 * structured data mappings have one.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class OrganizationData extends Model implements StructuredDataFormModel {
    public ?string $name = null;
    public ?string $legalName = null;

    /**
     * @var string|null schema.org `Organization.slogan`.
     *
     * @since v1.5.0
     */
    public ?string $slogan = null;

    /**
     * @var string|null schema.org `Organization.description` - a short description of the
     * organization.
     *
     * @since v1.5.0
     */
    public ?string $description = null;

    public ?string $url = null;

    /**
     * @var array `editableTableField` rows, each `{contactType: string, telephone: string,
     * email: string}` - stored and redisplayed as-is, the same way `$sameAs` is. Flattened into
     * schema.org `ContactPoint` objects in `toProperties()`, replacing what used to be flat
     * `email`/`telephone` properties.
     *
     * @since v1.5.0
     */
    public array $contactPoints = [];

    /**
     * @var string|null schema.org `Organization.foundingDate`, e.g. `2008-01-01` or just a
     * year. Kept as free text (not a native date picker) since schema.org accepts any of a
     * handful of date formats.
     *
     * @since v1.5.0
     */
    public ?string $foundingDate = null;

    /**
     * @var string|null schema.org `Organization.vatID` - the VAT registration number.
     *
     * @since v1.5.0
     */
    public ?string $vatID = null;

    /**
     * @var string|null schema.org `Organization.taxID` - a national tax identification number.
     *
     * @since v1.5.0
     */
    public ?string $taxID = null;

    /**
     * @var string|null schema.org `PostalAddress.streetAddress` - the street name and number.
     *
     * @since v1.5.0
     */
    public ?string $streetAddress = null;

    /**
     * @var string|null schema.org `PostalAddress.extendedAddress` - an apartment/suite number,
     * C/O, or other address extension.
     *
     * @since v1.5.0
     */
    public ?string $extendedAddress = null;

    /**
     * @var string|null schema.org `PostalAddress.postOfficeBoxNumber` - for PO box addresses.
     *
     * @since v1.5.0
     */
    public ?string $postOfficeBoxNumber = null;

    /**
     * @var string|null schema.org `PostalAddress.addressLocality` - the city/town.
     *
     * @since v1.5.0
     */
    public ?string $addressLocality = null;

    /**
     * @var string|null schema.org `PostalAddress.addressRegion` - the state/province/region.
     *
     * @since v1.5.0
     */
    public ?string $addressRegion = null;

    /**
     * @var string|null schema.org `PostalAddress.postalCode`.
     *
     * @since v1.5.0
     */
    public ?string $postalCode = null;

    /**
     * @var string|null schema.org `PostalAddress.addressCountry` - the two-letter ISO
     * 3166-1 alpha-2 country code.
     *
     * @since v1.5.0
     */
    public ?string $addressCountry = null;

    /**
     * @var string|null The address's map location latitude, as posted by its text field.
     *
     * @since v1.5.0
     */
    public ?string $latitude = null;

    /**
     * @var string|null The address's map location longitude, as posted by its text field.
     *
     * @since v1.5.0
     */
    public ?string $longitude = null;

    /**
     * @var array `editableTableField`'s own row shape (keyed by row ID, each a `{url: string}`
     * row) - stored and redisplayed as-is, the same way `ContentSeoService` keeps
     * `titleConfig`/`descriptionConfig` rows rather than flattening them. Only flattened down to
     * a plain string list in `toProperties()`, where it matters.
     *
     * @since v1.5.0
     */
    public array $sameAs = [];

    /**
     * @var mixed `elementSelectField`'s own posted shape - an array holding the one selected
     * asset ID, or `''` via its hidden fallback input when nothing's selected. Left exactly as
     * posted (same as `Settings::$faviconAssetId`) rather than normalized up front, since
     * `sourceData` needs to redisplay it to `elementSelectField` as-is on the next edit anyway.
     *
     * @since v1.5.0
     */
    public mixed $logo = null;

    /**
     * @var mixed `elementSelectField`'s own posted shape, same as `$logo` - schema.org
     * `Organization.image`, a general representative image (as opposed to `$logo`'s wordmark).
     *
     * @since v1.5.0
     */
    public mixed $image = null;

    /**
     * @var string|null Raw JSON object merged into `toProperties()`'s result last, overriding
     * any matching key - the escape hatch for schema.org `Organization` properties (or
     * overrides) this settings page has no field for.
     *
     * @since v1.5.0
     */
    public ?string $additionalProperties = null;

    /**
     * @return Asset|null
     *
     * @since v1.5.0
     */
    public function getLogoAsset(): ?Asset {
        $id = \is_array($this->logo) ? reset($this->logo) : $this->logo;

        return is_numeric($id) ? Asset::find()->id((int)$id)->one() : null;
    }

    /**
     * @return Asset|null
     *
     * @since v1.5.0
     */
    public function getImageAsset(): ?Asset {
        $id = \is_array($this->image) ? reset($this->image) : $this->image;

        return is_numeric($id) ? Asset::find()->id((int)$id)->one() : null;
    }

    /**
     * @inheritdoc
     * @return array
     *
     * @since v1.5.0
     */
    public function defineRules(): array {
        return [
            [['name'], 'required'],
            [['name', 'legalName', 'slogan', 'foundingDate', 'vatID', 'taxID'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 1000],
            [['streetAddress', 'extendedAddress', 'postOfficeBoxNumber', 'addressLocality', 'addressRegion', 'postalCode'], 'string', 'max' => 255],
            [['addressCountry'], 'string', 'max' => 2],
            [['latitude', 'longitude'], 'number'],
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
     * Builds the `Organization` schema.org properties this object's fields map to - the fixed
     * counterpart to a section mapping's Twig templates. A field left empty is omitted entirely
     * rather than kept as `""`/`null`, the same "no value, no property" rule
     * `StructuredDataService::renderProperties()` applies to a rendered template.
     *
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    public function toProperties(): array {
        $properties = array_filter([
            'name' => $this->name,
            'legalName' => $this->legalName,
            'slogan' => $this->slogan,
            'description' => $this->description,
            'url' => $this->url,
            'foundingDate' => $this->foundingDate,
            'vatID' => $this->vatID,
            'taxID' => $this->taxID,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        $logoUrl = $this->getLogoAsset()?->getUrl();
        if ($logoUrl !== null) {
            $properties['logo'] = $logoUrl;
        }

        $imageUrl = $this->getImageAsset()?->getUrl();
        if ($imageUrl !== null) {
            $properties['image'] = $imageUrl;
        }

        $address = $this->addressProperties();
        if ($address !== []) {
            $properties['address'] = $address;
        }

        $sameAs = $this->sameAsUrls();
        if ($sameAs !== []) {
            $properties['sameAs'] = $sameAs;
        }

        $contactPoints = $this->contactPointsData();
        if ($contactPoints !== []) {
            $properties['contactPoint'] = $contactPoints;
        }

        return array_merge($properties, $this->additionalPropertiesData());
    }

    /**
     * Builds the `PostalAddress` schema.org object from the address fields, for
     * `toProperties()`, via `spatie/schema-org`'s fluent builder rather than hand-assembling the
     * array - its setters already drop `null`/`''` values on their own, the same "no value, no
     * property" rule the rest of this object's properties follow. `[]` when every part is empty.
     *
     * @return array<string, string>
     *
     * @since v1.5.0
     */
    protected function addressProperties(): array {
        $address = CoreSeoGeo::getInstance()->getSchemaOrg()->build('PostalAddress')
            ->streetAddress($this->streetAddress)
            ->extendedAddress($this->extendedAddress)
            ->postOfficeBoxNumber($this->postOfficeBoxNumber)
            ->addressLocality($this->addressLocality)
            ->addressRegion($this->addressRegion)
            ->postalCode($this->postalCode)
            ->addressCountry($this->addressCountry);

        if ($address->getProperties() === []) return [];

        $properties = $address->toArray();
        unset($properties['@context']);

        return $properties;
    }

    /**
     * Decodes `$additionalProperties` for `toProperties()`'s final merge. `[]` for anything
     * that isn't a JSON object - empty/unset, invalid JSON, or a JSON array - rather than
     * throwing, since `defineRules()`'s validator is what surfaces that to the user; this just
     * has to be safe to call on an unvalidated model too (e.g. a live preview).
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

    /**
     * Flattens `$sameAs`'s `editableTableField` rows down to a plain list of non-empty URLs, for
     * `toProperties()`.
     *
     * @return string[]
     *
     * @since v1.5.0
     */
    protected function sameAsUrls(): array {
        return array_values(array_filter(array_map(
            static fn(mixed $row): string => trim((string)(\is_array($row) ? ($row['url'] ?? '') : '')),
            $this->sameAs,
        )));
    }

    /**
     * Flattens `$contactPoints`'s `editableTableField` rows into schema.org `ContactPoint`
     * objects via `spatie/schema-org`, for `toProperties()`. A row missing every part is
     * dropped, the same "no value, no property" rule the rest of this object's properties
     * follow.
     *
     * @return array<int, array<string, string>>
     *
     * @since v1.5.0
     */
    protected function contactPointsData(): array {
        $points = [];

        foreach ($this->contactPoints as $row) {
            if (!\is_array($row)) continue;

            $point = CoreSeoGeo::getInstance()->getSchemaOrg()->build('ContactPoint')
                ->contactType(trim((string)($row['contactType'] ?? '')))
                ->telephone(trim((string)($row['telephone'] ?? '')))
                ->email(trim((string)($row['email'] ?? '')));

            if ($point->getProperties() === []) continue;

            $properties = $point->toArray();
            unset($properties['@context']);

            $points[] = $properties;
        }

        return $points;
    }
}
