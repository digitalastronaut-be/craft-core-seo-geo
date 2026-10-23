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

use craft\base\Component;

/**
 * Class TxtFilesService
 *
 * Holds the slug/label pairs for the plugin's generated text files - robots.txt, humans.txt,
 * ads.txt, security.txt, trust.txt, llms.txt, llms-full.txt - shared between
 * `TxtFilesController`'s settings pages and the "Text Files" table on the Global SEO/GEO
 * settings page, same role
 * `GlobalStructuredDataService::TYPE_SLUGS` plays for the structured data table above it. No
 * generation or front-end serving logic lives here yet - that arrives per file, starting with
 * robots.txt.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.10.0
 */
class TxtFilesService extends Component {
    /**
     * @var array<string, string> URL slug => display label
     */
    public const array TYPE_SLUGS = [
        'robots' => 'robots.txt',
        'humans' => 'humans.txt',
        'ads' => 'ads.txt',
        'security' => 'security.txt',
        'trust' => 'trust.txt',
        'llms' => 'llms.txt',
        'llms-full' => 'llms-full.txt',
    ];

    /**
     * @param string $slug
     * @return string|null `null` if `$slug` isn't a recognized key of {@see TYPE_SLUGS}
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function getTypeLabel(string $slug): ?string {
        return self::TYPE_SLUGS[$slug] ?? null;
    }
}
