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

/**
 * Class LocalBusinessData
 *
 * The settings-page form model behind the global `LocalBusiness` structured data object.
 * `LocalBusiness` is schema.org's own specialization of `Organization` (it adds opening hours
 * and a price range on top of the same name/address/contact/map-location fields) so this model
 * extends `OrganizationData` rather than duplicating those fields, and layers its own on top in
 * `toProperties()`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class LocalBusinessData extends OrganizationData {
    public ?string $priceRange = null;

    /**
     * @var array `editableTableField` rows, each `{dayOfWeek: string, opens: string, closes:
     * string}` - stored and redisplayed as-is, flattened in `toProperties()`.
     *
     * @since v1.5.0
     */
    public array $openingHours = [];

    /**
     * @inheritdoc
     * @return array
     *
     * @since v1.5.0
     */
    public function defineRules(): array {
        return array_merge(parent::defineRules(), [
            [['priceRange'], 'string', 'max' => 255],
        ]);
    }

    /**
     * @inheritdoc
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    public function toProperties(): array {
        $properties = parent::toProperties();

        if ($this->priceRange !== null && $this->priceRange !== '') {
            $properties['priceRange'] = $this->priceRange;
        }

        if ($this->latitude !== null && $this->latitude !== '' && $this->longitude !== null && $this->longitude !== '') {
            $properties['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float)$this->latitude,
                'longitude' => (float)$this->longitude,
            ];
        }

        $openingHours = $this->_openingHoursSpecifications();
        if ($openingHours !== []) {
            $properties['openingHoursSpecification'] = $openingHours;
        }

        return $properties;
    }

    /**
     * Flattens `$openingHours`'s `editableTableField` rows into `OpeningHoursSpecification`
     * objects, dropping any row missing a day or both times.
     *
     * @return array<int, array{'@type': string, dayOfWeek: string, opens: string, closes: string}>
     *
     * @since v1.5.0
     */
    private function _openingHoursSpecifications(): array {
        $specs = [];

        foreach ($this->openingHours as $row) {
            if (!\is_array($row)) continue;

            $dayOfWeek = trim((string)($row['dayOfWeek'] ?? ''));
            $opens = trim((string)($row['opens'] ?? ''));
            $closes = trim((string)($row['closes'] ?? ''));

            if ($dayOfWeek === '' || $opens === '' || $closes === '') continue;

            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $dayOfWeek,
                'opens' => $opens,
                'closes' => $closes,
            ];
        }

        return $specs;
    }
}
