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

/**
 * Class WebsiteData
 *
 * The settings-page form model behind the global `WebSite` structured data object.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class WebsiteData extends Model implements StructuredDataFormModel {
    public ?string $name = null;
    public ?string $url = null;
    public ?string $description = null;

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
            [['url'], 'url', 'defaultScheme' => 'https'],
        ];
    }

    /**
     * Builds the `WebSite` schema.org properties this object's fields map to.
     *
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    public function toProperties(): array {
        return array_filter([
            'name' => $this->name,
            'url' => $this->url,
            'description' => $this->description,
        ], static fn(?string $value): bool => $value !== null && $value !== '');
    }
}
