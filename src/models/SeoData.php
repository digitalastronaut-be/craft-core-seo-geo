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

/**
 * Class SeoData
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SeoData extends Model {
    // Const Properties
    // =========================================================================

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
     * @var string[] Every directive the `robots` property accepts.
     *
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
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $canonicalUrl = null;
    public array $robots = [];

    /**
     * @var int|null The ID of the general-purpose asset for this page, used for things like
     * the `og:image` meta tag.
     *
     * @since v1.0.0
     */
    public ?int $imageId = null;

    /**
     * @var string[] Raw per-row override text for the field's `titleFormatRows`, aligned by
     * index, so the CP input can be repopulated on the next edit. The computed `metaTitle`
     * is what everything else should read.
     *
     * @since v1.0.0
     */
    public array $titleRows = [];

    /**
     * @var string[] Raw per-row override text for the field's `descriptionFormatRows`. See
     * {@see SeoData::$titleRows}.
     *
     * @since v1.0.0
     */
    public array $descriptionRows = [];

    /**
     * @return bool
     * @since       v1.0.0
     */
    public function isEmpty(): bool {
        return $this->metaTitle === null
            && $this->metaDescription === null
            && $this->canonicalUrl === null
            && $this->robots === []
            && $this->imageId === null;
    }
}
