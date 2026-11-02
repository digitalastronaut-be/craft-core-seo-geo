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
     * Matches `coreseogeo_seoscores.metaTitle`/`.metaDescription` ($this->string(500)) - the
     * denormalized copy `SeoScoreService` writes these into. Enforced at save time
     * (`SeoField::validateSeoData()`) so a resolved value that would fail that write can never
     * be saved in the first place.
     *
     * @since v1.0.7
     */
    public const int MAX_META_LENGTH = 500;

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
     * Whether this entry should be left out of the sitemap - an additional, per-entry exclusion
     * layer on top of the global ({@see \digitalastronaut\craftcoreseogeo\models\Settings::$enableSitemap})
     * and content-level ({@see \digitalastronaut\craftcoreseogeo\records\ContentSeo::$sitemapEnabled})
     * toggles. Like those two, this only ever removes an entry that would otherwise be
     * included - turning it off can't force an entry back in when the site or its section has
     * sitemap generation disabled. Defaults to `false` so an entry never silently drops out of
     * the sitemap just because it predates this setting. Named (and defaulted) the other way
     * around from the other two levels on purpose: those are coarse, rarely-touched feature
     * toggles ("is this mechanism switched on"), where "enabled" is the natural default framing.
     * This one's a frequent, per-entry editorial decision - the same shape as the `robots`
     * field's `noindex` option right next to it in the CP - so an opt-out framing ("exclude this
     * one") reads far more naturally than an inverted "include" switch that defaults to on.
     *
     * @since v1.37.0
     */
    public bool $excludeFromSitemap = false;

    /**
     * @return AssetQuery
     *
     * @since v1.0.0
     */
    public function getImage(): AssetQuery {
        return Asset::find()->id($this->_imageId ?? false);
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
            $this->_imageId === null &&
            $this->excludeFromSitemap === false;
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
