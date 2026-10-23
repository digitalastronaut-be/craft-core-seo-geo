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
 * Class PersonData
 *
 * The settings-page form model behind the global `Person` structured data object - the smallest
 * of the four built-in types, mainly useful for a personal site or a company blog's "about the
 * author" byline.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class PersonData extends Model implements StructuredDataFormModel {
    public ?string $name = null;
    public ?string $jobTitle = null;
    public ?string $url = null;
    public ?string $email = null;
    public ?string $telephone = null;
    public ?string $worksFor = null;

    /**
     * @var array `editableTableField` rows, each a `{url: string}` row - see
     * `OrganizationData::$sameAs`.
     *
     * @since v1.5.0
     */
    public array $sameAs = [];

    /**
     * @var mixed `elementSelectField`'s own posted shape - see `OrganizationData::$logo`.
     *
     * @since v1.5.0
     */
    public mixed $image = null;

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
            [['name', 'jobTitle', 'telephone', 'worksFor'], 'string', 'max' => 255],
            [['url'], 'url', 'defaultScheme' => 'https'],
            [['email'], 'email'],
        ];
    }

    /**
     * Builds the `Person` schema.org properties this object's fields map to.
     *
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    public function toProperties(): array {
        $properties = array_filter([
            'name' => $this->name,
            'jobTitle' => $this->jobTitle,
            'url' => $this->url,
            'email' => $this->email,
            'telephone' => $this->telephone,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        if ($this->worksFor !== null && $this->worksFor !== '') {
            $properties['worksFor'] = ['@type' => 'Organization', 'name' => $this->worksFor];
        }

        $imageUrl = $this->getImageAsset()?->getUrl();
        if ($imageUrl !== null) {
            $properties['image'] = $imageUrl;
        }

        $sameAs = array_values(array_filter(array_map(
            static fn(mixed $row): string => trim((string)(\is_array($row) ? ($row['url'] ?? '') : '')),
            $this->sameAs,
        )));

        if ($sameAs !== []) {
            $properties['sameAs'] = $sameAs;
        }

        return $properties;
    }
}
