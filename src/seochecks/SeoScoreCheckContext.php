<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\seochecks;

use craft\base\ElementInterface;

use digitalastronaut\craftcoreseogeo\models\SeoData;

/**
 * Class SeoScoreCheckContext
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
final class SeoScoreCheckContext {
    /**
     * @param ElementInterface $element 
     * @param SeoData $seoData 
     * @param int $fieldId
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function __construct(
        public readonly ElementInterface $element,
        public readonly SeoData $seoData,
        public readonly int $fieldId,
    ) {
    }
}
