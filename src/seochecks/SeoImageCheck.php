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

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class SeoImageCheck
 *
 * Fails when no SEO image is set, after falling back to the element's section (or site-wide)
 * default image - see `ContentSeoService::getFallbackImage()` - the same image the rendered
 * og:image/twitter:image tags themselves fall back to. A set image missing alt text, or smaller
 * than the common Open Graph minimum, is a warning rather than a failure -- it still earns the
 * check's full weight, but the message surfaces in the CP so it doesn't go unnoticed.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class SeoImageCheck implements SeoScoreCheckInterface {
    /**
     * @since v1.0.4
     */
    private const int MIN_WIDTH = 1200;
    private const int MIN_HEIGHT = 630;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getKey(): string {
        return 'seo-image';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'SEO image');
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
        $asset = $context->seoData->getImage()->one()
            ?? CoreSeoGeo::getInstance()->getContentSeo()->getFallbackImage($context->element);

        if ($asset === null) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'No SEO image is set.'));
        }

        if ($asset->alt === null || trim($asset->alt) === '') {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Warning, Craft::t('core-seo-geo', 'The SEO image is set, but has no alt text.'));
        }

        $width = $asset->getWidth();
        $height = $asset->getHeight();

        if ($width !== null && $height !== null && ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT)) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Warning, Craft::t('core-seo-geo', 'The SEO image is {width}x{height}px. {minWidth}x{minHeight}px or larger is recommended for social sharing.', [
                'width' => $width,
                'height' => $height,
                'minWidth' => self::MIN_WIDTH,
                'minHeight' => self::MIN_HEIGHT,
            ]));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'The SEO image is set, with alt text and a good size.'));
    }
}
