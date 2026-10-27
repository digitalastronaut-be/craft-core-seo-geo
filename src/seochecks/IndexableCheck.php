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

use Craft;

use digitalastronaut\craftcoreseogeo\models\SeoData;

/**
 * Class IndexableCheck
 *
 * Not applicable when `noindex` is set -- that's often deliberate (a thank-you page, an internal
 * utility page), not a mistake, so it's excluded from the score entirely rather than scored
 * either way. There's no failure state: a page is either indexable (passes) or intentionally
 * excluded from search (not applicable).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.5
 */
class IndexableCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function getKey(): string {
        return 'indexable';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Indexable');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function getWeight(): int {
        return 10;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        if (in_array(SeoData::ROBOTS_NOINDEX, $context->seoData->robots, true)) {
            return new SeoScoreCheckResult(
                SeoScoreCheckStatus::Passed,
                Craft::t('core-seo-geo', 'This page is set to noindex, so it is intentionally excluded from search results.'),
                false,
            );
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'This page is indexable by search engines.'));
    }
}
