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
 * Class ImageAltTextCheck
 *
 * Fails when an `<img>` inside the rendered page's `<main>` has no `alt` attribute, or an
 * empty one. Scoped to `<main>` deliberately -- decorative images in shared chrome (header/
 * footer) are a template concern, not this entry's content. Not applicable if there's no
 * document to check, or no images inside `<main>` at all.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.30.0
 */
class ImageAltTextCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getKey(): string {
        return 'image-alt-text';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Content images have alt text');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.35.0
     */
    public function getDescription(): string {
        return Craft::t('core-seo-geo', 'Checks every <img> inside the page\'s <main> element for a non-empty alt attribute. Decorative images in shared chrome (header/footer) are out of scope. Not applicable if the page can\'t be fetched or <main> has no images. Fails if any image inside <main> is missing alt text.');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getWeight(): int {
        return 10;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        $document = $context->getDocument();

        if ($document === null) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        $images = (new \DOMXPath($document))->query('//*[local-name()="main"]//*[local-name()="img"]');

        if ($images->length === 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        $missingAlt = 0;

        foreach ($images as $image) {
            /** @var \DOMElement $image */
            if (!$image->hasAttribute('alt') || trim($image->getAttribute('alt')) === '') $missingAlt++;
        }

        if ($missingAlt > 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', '{count, plural, =1{One image in the content} other{# images in the content}} has no alt text.', [
                'count' => $missingAlt,
            ]));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'Every content image has alt text.'));
    }
}
