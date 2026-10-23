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

/**
 * Class SeoScoreCheckResult
 *
 * The outcome of running one {@see SeoScoreCheckInterface} against one
 * {@see SeoScoreCheckContext}. Deliberately doesn't carry the check's weight or label -- those
 * are static facts about the check itself ({@see SeoScoreCheckInterface::getWeight()}), not
 * something a single run computes, so `SeoScoreService` pairs them back in itself rather than
 * having every check implementation repeat its own weight here.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
final class SeoScoreCheckResult {
    /**
     * @param bool $valid whether the check passed
     * @param string $message a human-readable explanation of the result, shown in the CP
     * @param bool $applicable whether this check applies at all for the element checked (e.g. a
     * canonical URL check is not applicable when no canonical override is set). A non-applicable
     * result is excluded from the score entirely -- it's neither a pass nor a fail.
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function __construct(
        public readonly bool $valid,
        public readonly string $message,
        public readonly bool $applicable = true,
    ) {
    }
}
