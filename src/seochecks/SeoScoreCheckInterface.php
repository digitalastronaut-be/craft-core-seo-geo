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
     * A longer explanation of what this check looks at and the messages it can return, shown as
     * a tooltip next to the check's label on the global SEO settings page.
     *
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.35.0
     */
    public function getDescription(): string;

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
