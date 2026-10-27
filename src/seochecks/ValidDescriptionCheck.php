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

/**
 * Class ValidDescriptionCheck
 *
 * Fails when the resolved meta description is empty. A description outside the recommended
 * length is a warning rather than a failure -- it still earns the check's full weight, but the
 * message surfaces in the CP so it doesn't go unnoticed.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class ValidDescriptionCheck implements SeoScoreCheckInterface {
    /**
     * @since v1.0.4
     */
    private const int MIN_LENGTH = 50;
    private const int MAX_LENGTH = 160;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getKey(): string {
        return 'valid-description';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Valid description');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getWeight(): int {
        return 20;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        $description = $context->seoData->metaDescription;

        if ($description === null || trim($description) === '') {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'No meta description is set.'));
        }

        $length = mb_strlen($description);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Warning, Craft::t('core-seo-geo', 'The meta description is {length} characters. {min} to {max} is recommended.', [
                'length' => $length,
                'min' => self::MIN_LENGTH,
                'max' => self::MAX_LENGTH,
            ]));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'The meta description is set and a good length.'));
    }
}
