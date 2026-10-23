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

use yii\validators\UrlValidator;

/**
 * Class CanonicalUrlCheck
 *
 * Not applicable when no canonical URL override is set -- that's the common case, not a failure.
 * When one is set, re-validates its format (the field itself already blocks saving
 * an invalid one, but this is cheap insurance against data written by another path, e.g. the
 * element API or GraphQL mutations) and notes whether it points somewhere other than the
 * element's own URL -- sometimes intentional, so it's informational rather than a failure.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class CanonicalUrlCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getKey(): string {
        return 'canonical-url';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Canonical URL');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getWeight(): int {
        return 10;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        $canonicalUrl = $context->seoData->canonicalUrl;

        if ($canonicalUrl === null) {
            return new SeoScoreCheckResult(true, Craft::t('core-seo-geo', 'No canonical URL override is set.'), false);
        }

        $validator = new UrlValidator(['defaultScheme' => 'https']);

        if (!$validator->validate($canonicalUrl)) {
            return new SeoScoreCheckResult(false, Craft::t('core-seo-geo', 'The canonical URL is not a valid URL.'));
        }

        $elementUrl = $context->element->getUrl();

        if ($elementUrl !== null && rtrim($canonicalUrl, '/') !== rtrim($elementUrl, '/')) {
            return new SeoScoreCheckResult(true, Craft::t('core-seo-geo', 'The canonical URL points elsewhere ({url}), which may be intentional.', [
                'url' => $canonicalUrl,
            ]));
        }

        return new SeoScoreCheckResult(true, Craft::t('core-seo-geo', 'The canonical URL matches the page\'s own URL.'));
    }
}
