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

use craft\base\Model;
use craft\elements\Asset;

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
 * The postal address itself isn't a field here - it's the real, native `craft\fields\Addresses`
 * field `StructuredData::BUSINESS_ADDRESS_FIELD_HANDLE` names, added to the owning element's own
 * field layout. `GlobalStructuredDataService::save()` is what folds that into `address`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class OrganizationData extends Model implements StructuredDataFormModel {
    public ?string $name = null;
    public ?string $legalName = null;
    public ?string $url = null;
    public ?string $email = null;
    public ?string $telephone = null;

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
     * @return Asset|null
     *
     * @since v1.5.0
     */
    public function getLogoAsset(): ?Asset {
        $id = \is_array($this->logo) ? reset($this->logo) : $this->logo;

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
            [['name', 'legalName', 'telephone'], 'string', 'max' => 255],
            [['url'], 'url', 'defaultScheme' => 'https'],
            [['email'], 'email'],
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
            'url' => $this->url,
            'email' => $this->email,
            'telephone' => $this->telephone,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        $logoUrl = $this->getLogoAsset()?->getUrl();
        if ($logoUrl !== null) {
            $properties['logo'] = $logoUrl;
        }

        $sameAs = $this->sameAsUrls();
        if ($sameAs !== []) {
            $properties['sameAs'] = $sameAs;
        }

        return $properties;
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
}
