<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models;

use craft\base\Model;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;

/**
 * Class SeoData
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SeoData extends Model {
    /**
     * @since v1.0.0
    */
    public const string ROBOTS_NOINDEX = 'noindex';
    public const string ROBOTS_NOFOLLOW = 'nofollow';
    public const string ROBOTS_NOARCHIVE = 'noarchive';
    public const string ROBOTS_NOSNIPPET = 'nosnippet';
    public const string ROBOTS_NOTRANSLATE = 'notranslate';
    public const string ROBOTS_NOIMAGEINDEX = 'noimageindex';

    /**
     * @since v1.0.0
     */
    public const string ROBOTS_NOAI = 'noai';
    public const string ROBOTS_NOIMAGEAI = 'noimageai';

    /**
     * @since v1.0.0
     */
    public const array ROBOTS_DIRECTIVES = [
        self::ROBOTS_NOINDEX,
        self::ROBOTS_NOFOLLOW,
        self::ROBOTS_NOARCHIVE,
        self::ROBOTS_NOSNIPPET,
        self::ROBOTS_NOTRANSLATE,
        self::ROBOTS_NOIMAGEINDEX,
        self::ROBOTS_NOAI,
        self::ROBOTS_NOIMAGEAI,
    ];

    /**
     * @since v1.0.0
     */
    private ?int $_imageId = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $canonicalUrl = null;
    public array $titleRows = [];
    public array $descriptionRows = [];
    public array $robots = [];

    /**
     * @return AssetQuery
     *
     * @since v1.0.0
     */
    public function getImage(): AssetQuery {
        return Asset::find()->andWhere(['elements.id' => $this->_imageId]);
    }

    /**
     * @param Asset|int|string|null $value
     * @return void
     *
     * @since v1.0.0
     */
    public function setImage(Asset|int|string|null $value): void {
        $this->_imageId = $value instanceof Asset ? $value->id : (is_numeric($value) ? (int)$value : null);
    }

    /**
     * @inheritdoc
     * @return array
     *
     * @since v1.0.0
     */
    public function fields(): array {
        return [...parent::fields(), 'image' => fn(): ?int => $this->_imageId];
    }

    /**
     * @return bool
     * @since       v1.0.0
     */
    public function isEmpty(): bool {
        return 
            $this->metaTitle === null && 
            $this->metaDescription === null && 
            $this->canonicalUrl === null && 
            $this->robots === [] && 
            $this->_imageId === null;
    }

    /**
     * @return array
     *
     * @since v1.0.0
     */
    public function __debugInfo(): array {
        return ['image' => $this->getImage()];
    }
}
