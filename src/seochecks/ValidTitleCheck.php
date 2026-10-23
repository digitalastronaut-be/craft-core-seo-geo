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
 * Class ValidTitleCheck
 *
 * Fails when the resolved meta title is empty. A title outside the recommended length still
 * passes -- it's noted in the message, not scored as a failure (see the "binary pass/fail"
 * decision on `SeoScoreCheckResult`).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class ValidTitleCheck implements SeoScoreCheckInterface {
    /**
     * @since v1.0.4
     */
    private const int MIN_LENGTH = 10;
    private const int MAX_LENGTH = 60;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getKey(): string {
        return 'valid-title';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Valid title');
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
        $title = $context->seoData->metaTitle;

        if ($title === null || trim($title) === '') {
            return new SeoScoreCheckResult(false, Craft::t('core-seo-geo', 'No meta title is set.'));
        }

        $length = mb_strlen($title);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return new SeoScoreCheckResult(true, Craft::t('core-seo-geo', 'The meta title is {length} characters. {min} to {max} is recommended.', [
                'length' => $length,
                'min' => self::MIN_LENGTH,
                'max' => self::MAX_LENGTH,
            ]));
        }

        return new SeoScoreCheckResult(true, Craft::t('core-seo-geo', 'The meta title is set and a good length.'));
    }
}
