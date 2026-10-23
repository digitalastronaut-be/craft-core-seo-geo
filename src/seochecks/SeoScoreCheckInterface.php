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
 * Interface SeoScoreCheckInterface
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
interface SeoScoreCheckInterface {
    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getKey(): string;

    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getLabel(): string;

    /**
     * @return int
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getWeight(): int;

    /**
     * @param SeoScoreCheckContext $context
     * @return SeoScoreCheckResult
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult;
}
