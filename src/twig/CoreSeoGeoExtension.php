<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\twig;

use Twig\Extension\AbstractExtension;

/**
 * Class CoreSeoGeoExtension
 *
 * This plugin's Twig extension. Currently registers the `{% llmContent %}`/
 * `{% excludeLlmContent %}` tags templates use to mark which regions of a page count as
 * content for its Markdown representation.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class CoreSeoGeoExtension extends AbstractExtension {
    /**
     * @return array
     */
    public function getTokenParsers(): array {
        return [
            new LlmContentTokenParser(),
            new ExcludeLlmContentTokenParser(),
        ];
    }
}
